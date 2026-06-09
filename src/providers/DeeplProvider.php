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

class DeeplProvider extends Provider
{
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
        $glossary = Glossary::find()->where([
            'sourceLanguage' => substr($sourceLocale, 0, 2),
            'targetLanguage' => substr($targetLocale, 0, 2),
            'enabled' => 1,
        ])->one();

        $modelType = $this->getSetting('deeplModelType', 'latency_optimized');

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

        if ($text) {
            return $this->getClient()->translateText($text, $this->sourceLocale($sourceLocale), $this->targetLocale($targetLocale), $defaultOptions);
        }

        return null;
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
