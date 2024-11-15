<?php

namespace digitalpulsebe\craftmultitranslator\services;

use craft\helpers\App;
use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
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
            $this->_client = new Translator($apiKey);;
        }

        return $this->_client;
    }

    public function translate(string $sourceLocale = null, string $targetLocale = null, string $text = null): ?string
    {
        $glossary = Glossary::find()->where([
            'sourceLanguage' => substr($sourceLocale, 0, 2),
            'targetLanguage' => substr($targetLocale, 0, 2),
        ])->one();

        $defaultOptions = [
            'tag_handling' => 'html',
            'formality' => $this->getProviderSettings()->getDeeplFormality(),
            'preserve_formatting' => $this->getProviderSettings()->getDeeplPreserveFormatting(),
        ];

        if ($glossary) {
            $defaultOptions['glossary'] = $glossary->deeplId;
        }

        if ($text) {
            return $this->getClient()->translateText($text, $this->sourceLocale($sourceLocale), $this->targetLocale($targetLocale), $defaultOptions);
        }

        return null;
    }

    public function listGlossaries()
    {
        return $this->getClient()->listGlossaries();
    }

    public function createGlossary(string $name, string $sourceLanguage, string $targetLanguage, array $data): GlossaryInfo
    {
        $entries = GlossaryEntries::fromEntries($data);

        return $this->getClient()->createGlossary($name, $sourceLanguage, $targetLanguage, $entries);
    }

    public function deleteGlossary(string $id): void
    {
        $this->getClient()->deleteGlossary($id);
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
