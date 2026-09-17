<?php

namespace digitalpulsebe\craftmultitranslator\providers;

use craft\helpers\App;
use DeepL\DeepLClient;
use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\Translator;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\records\Glossary;
use digitalpulsebe\craftmultitranslator\records\StyleRule;

class DeeplProvider extends Provider
{
    // target languages supported by the custom_instructions option (including their regional variants)
    private const CUSTOM_INSTRUCTION_LANGUAGES = ['de', 'en', 'es', 'fr', 'it', 'ja', 'ko', 'zh'];

    protected ?Translator $_client = null;

    public static function getHandle(): string
    {
        return 'deepl';
    }

    public static function getDisplayName(): string
    {
        return 'DeepL';
    }

    public function getSettingsTemplatePath(): ?string
    {
        return 'multi-translator/_providers/deepl/_settings';
    }

    public function isConnected(): bool
    {
        try {
            $this->getClient()->getUsage();
            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function getClient(): DeepLClient
    {
        if (!$this->_client) {
            $apiKey = App::parseEnv($this->getSetting('deeplApiKey', ''));
            $this->_client = new DeepLClient($apiKey);
        }

        return $this->_client;
    }

    public function translate(string $sourceLocale = null, string $targetLocale = null, string $text = null): ?string
    {
        if (!$text) {
            return null;
        }

        $deeplTarget = $this->targetLocale($targetLocale);
        $targetLanguage = strtolower(explode('-', $deeplTarget)[0]);

        $glossary = Glossary::find()->where([
            'sourceLanguage' => substr($sourceLocale, 0, 2),
            'targetLanguage' => substr($targetLocale, 0, 2),
            'enabled' => 1,
        ])->one();

        $styleRule = StyleRule::find()->where([
            'language' => $targetLanguage,
            'enabled' => 1,
        ])->one();

        $customInstructions = $this->customInstructionsForTarget($targetLanguage);

        $modelType = $this->getSetting('deeplModelType', 'latency_optimized');

        // custom instructions require a next-generation model,
        // the API documents requests combining them with model_type=latency_optimized as rejected
        if ($customInstructions && $modelType === 'latency_optimized') {
            $modelType = 'quality_optimized';
        }

        $defaultOptions = [
            'tag_handling' => 'html',
            'model_type' => $modelType,
            'formality' => $this->getSetting('deeplFormality', 'default'),
            'preserve_formatting' => (bool) $this->getSetting('deeplPreserveFormatting', false),
        ];

        // model_type=latency_optimized does not support tag_handling_version=v2
        if ($modelType === 'quality_optimized') {
            $defaultOptions['tag_handling_version'] = 'v2';
        } else {
            $defaultOptions['tag_handling_version'] = 'v1';
        }

        if ($glossary) {
            $defaultOptions['glossary'] = $glossary->deeplId;
        }

        if ($styleRule) {
            $defaultOptions['style_id'] = $styleRule->deeplId;
        }

        if ($customInstructions) {
            $defaultOptions['custom_instructions'] = $customInstructions;
        }

        return $this->getClient()->translateText($text, $this->sourceLocale($sourceLocale), $deeplTarget, $defaultOptions);
    }

    private function customInstructionsForTarget(string $targetLanguage): array
    {
        if (!in_array($targetLanguage, self::CUSTOM_INSTRUCTION_LANGUAGES, true)) {
            return [];
        }

        $rows = $this->getSetting('deeplCustomInstructions', []);

        // an empty editable table is stored as an empty string
        if (!is_array($rows)) {
            return [];
        }

        $instructions = [];

        foreach ($rows as $row) {
            $instruction = trim($row['instruction'] ?? '');

            if ($instruction !== '') {
                // the API allows at most 300 characters per instruction
                $instructions[] = mb_substr($instruction, 0, 300);
            }
        }

        // the API allows at most 10 instructions
        return array_slice($instructions, 0, 10);
    }

    public function fetchGlossaries(): void
    {
        $glossaries = $this->getClient()->listMultilingualGlossaries();
        $recordIds = [];

        foreach ($glossaries as $glossaryInfo) {
            foreach ($glossaryInfo->dictionaries as $dictionary) {
                $glossaryEntries = $this->getClient()->getMultilingualGlossaryEntries($glossaryInfo->glossaryId, $dictionary->sourceLang, $dictionary->targetLang);

                foreach ($glossaryEntries as $glossaryEntry) {
                    if ($glossaryEntry instanceof MultilingualGlossaryDictionaryEntries) {
                        $glossaryRecord = Glossary::findOne([
                            'deeplId' => $glossaryInfo->glossaryId,
                            'sourceLanguage' => $dictionary->sourceLang,
                            'targetLanguage' => $dictionary->targetLang,
                        ]);

                        if (!$glossaryRecord) {
                            $glossaryRecord = new Glossary();
                            $glossaryRecord->setAttribute('deeplId', $glossaryInfo->glossaryId);
                            $glossaryRecord->setAttribute('sourceLanguage', $glossaryEntry->sourceLang);
                            $glossaryRecord->setAttribute('targetLanguage', $glossaryEntry->targetLang);
                        }

                        $glossaryRecord->setAttribute('data', $glossaryEntry->entries);
                        $glossaryRecord->setAttribute('name', $glossaryInfo->name);
                        if (!$glossaryRecord->save()) {
                            throw new \Exception(json_encode($glossaryRecord->getErrors()));
                        }

                        $recordIds[] = $glossaryRecord->id;
                    }
                }
            }
        }

        Glossary::deleteAll(['not in', 'id', $recordIds]);
    }

    public function fetchStyleRules(): void
    {
        $styleRules = $this->getClient()->getAllStyleRules(null, null, true);
        $recordIds = [];

        foreach ($styleRules as $styleRuleInfo) {
            $styleRuleRecord = StyleRule::findOne(['deeplId' => $styleRuleInfo->styleId]);

            if (!$styleRuleRecord) {
                // new style rules arrive disabled, an admin has to enable them per language
                $styleRuleRecord = new StyleRule();
                $styleRuleRecord->setAttribute('deeplId', $styleRuleInfo->styleId);
                $styleRuleRecord->setAttribute('enabled', 0);
            }

            $styleRuleRecord->setAttribute('name', $styleRuleInfo->name);
            $styleRuleRecord->setAttribute('language', strtolower(explode('-', $styleRuleInfo->language)[0]));
            $styleRuleRecord->setAttribute('data', [
                'version' => $styleRuleInfo->version,
                'configuredRules' => $styleRuleInfo->configuredRules ? array_keys(array_filter((array) $styleRuleInfo->configuredRules)) : [],
                'customInstructions' => array_map(fn ($instruction) => $instruction->label, $styleRuleInfo->customInstructions ?? []),
            ]);

            if (!$styleRuleRecord->save()) {
                throw new \Exception(json_encode($styleRuleRecord->getErrors()));
            }

            $recordIds[] = $styleRuleRecord->id;
        }

        StyleRule::deleteAll(['not in', 'id', $recordIds]);
    }

    public function createGlossary(string $name, string $sourceLanguage, string $targetLanguage, array $data): GlossaryInfo
    {
        $entries = GlossaryEntries::fromEntries($data);

        return $this->getClient()->createGlossary($name, $sourceLanguage, $targetLanguage, $entries);
    }

    public function deleteGlossary(int $recordId): void
    {
        $record = Glossary::findOne(['id' => $recordId]);

        $glossaryInfo = $this->getClient()->getMultilingualGlossary($record->deeplId);

        if ($glossaryInfo) {
            foreach ($glossaryInfo->dictionaries as $dictionary) {
                if ($dictionary->sourceLang == $record->sourceLanguage && $dictionary->targetLang == $record->targetLanguage) {
                    $this->getClient()->deleteMultilingualGlossaryDictionary($glossaryInfo, $dictionary);
                }
            }
        }
    }

    public function getUsage(): \DeepL\Usage
    {
        return $this->getClient()->getUsage();
    }

    // =========================================================================
    // DeepL-specific locale overrides
    // =========================================================================

    public function sourceLocale(?string $raw): ?string
    {
        if (!empty($raw)) {
            return substr($raw, 0, 2);
        }

        return null;
    }

    public function targetLocale(string $raw): string
    {
        $ucRaw = strtoupper($raw);

        // matches zh-Hans|zh-Hant and all variants of zh-Hans-*|zh-Hant-*
        if (str_contains($ucRaw, 'ZH-HANS')) {
            return 'ZH-HANS';
        }
        if (str_contains($ucRaw, 'ZH-HANT')) {
            return 'ZH-HANT';
        }

        // site has regional locale that is supported by Deepl
        if (in_array($ucRaw, ['EN-GB', 'EN-US', 'PT-PT', 'PT-BR'])) {
            return $ucRaw;
        }

        // map regional Spanish to ES-419 Spanish (Latin American); except European Spanish
        if (str_contains($ucRaw, 'ES-') && !in_array($ucRaw, ['ES-ES', 'ES-IC', 'ES-EA'])) {
            return 'ES-419';
        }

        // all other languages only support non-regional locales
        $locale = strtoupper(explode('-', $raw)[0]);

        // English must always be regional
        if ($locale === 'EN') {
            return $this->getSetting('defaultEnglish', 'en-US');
        }

        // PT must always be regional
        if ($locale === 'PT') {
            return 'PT-PT';
        }

        // Deepl doesn't know NO
        if ($locale === 'NO') {
            return 'NB';
        }

        return $locale;
    }
}
