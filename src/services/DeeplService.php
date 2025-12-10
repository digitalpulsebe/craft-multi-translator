<?php

namespace digitalpulsebe\craftmultitranslator\services;

use craft\helpers\App;
use DeepL\DeepLClient;
use DeepL\DeepLException;
use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryInfo;
use DeepL\Translator;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\records\Glossary;

class DeeplService extends ApiService
{

    protected ?Translator $_client = null;

    public function getName(): string
    {
        return 'DeepL';
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

    public function getClient()
    {
        if (!$this->_client) {
            $apiKey = App::parseEnv($this->getProviderSettings()->getDeeplApiKey());
            $this->_client = new DeepLClient($apiKey);;
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

        $defaultOptions = [
            'tag_handling' => 'html',
            'model_type' => $this->getProviderSettings()->getDeeplModelType(),
            'formality' => $this->getProviderSettings()->getDeeplFormality(),
            'preserve_formatting' => $this->getProviderSettings()->getDeeplPreserveFormatting(),
        ];

        if ($this->getProviderSettings()->getDeeplModelType() == 'quality_optimized') {
            $defaultOptions['tag_handling_version'] = 'v2';
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

    public function sourceLocale($raw): ?string
    {
        if (!empty($raw)) {
            return substr($raw, 0, 2);
        }

        return null;
    }
    public function targetLocale($raw): string
    {
        if (in_array($raw, ['en-GB', 'en-US', 'pt-PT', 'pt-BR'])) {
            return $raw;
        }

        $locale = substr($raw, 0, 2);

        if ($locale == 'en') {
            return $this->getProviderSettings()->getDefaultEnglish();
        }

        if ($locale == 'pt') {
            return 'pt-PT';
        }

        return $locale;
    }

    public function getUsage(): \DeepL\Usage
    {
        return $this->getClient()->getUsage();
    }
}
