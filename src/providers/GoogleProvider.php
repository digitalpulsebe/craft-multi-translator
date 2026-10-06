<?php

namespace digitalpulsebe\craftmultitranslator\providers;

use craft\helpers\App;
use Google\Cloud\Translate\V2\TranslateClient;

class GoogleProvider extends Provider
{
    protected ?TranslateClient $_client = null;

    public static function getHandle(): string
    {
        return 'google';
    }

    public static function getDisplayName(): string
    {
        return 'Google Translate';
    }

    public function getSettingsTemplatePath(): ?string
    {
        return 'multi-translator/_providers/google/_settings';
    }

    public function isConnected(): bool
    {
        try {
            return $this->translate('en', 'nl', 'test') !== null;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function getClient(): TranslateClient
    {
        if (!$this->_client) {
            $apiKey = App::parseEnv($this->getSetting('googleApiKey', ''));
            $this->_client = new TranslateClient([
                'key' => $apiKey,
            ]);
        }

        return $this->_client;
    }

    public function translate(string $sourceLocale = null, string $targetLocale = null, string $text = null): ?string
    {
        if ($text) {
            $options = [
                'target' => $this->targetLocale($targetLocale),
            ];

            if ($sourceLocale) {
                $options['source'] = $sourceLocale;
            }

            $response = $this->getClient()->translate($text, $options);

            return html_entity_decode($response['text']);
        }

        return null;
    }

    public function supportsNativeArrayTranslation(): bool
    {
        return true;
    }

    public function getMaxArrayChunkItems(): ?int
    {
        // Not documented by Google. Community-reported "Too many text segment" errors
        // are commonly worked around by staying under ~100 segments per request; treat
        // this as a practical ceiling rather than an official spec.
        return 100;
    }

    public function getMaxArrayChunkChars(): int
    {
        // Cloud Translation - Basic (v2) documents a 100K byte max request size; kept
        // conservative to leave headroom for the rest of the request payload.
        return 90000;
    }

    public function translateArray(string $sourceLocale = null, string $targetLocale = null, array $texts = []): array
    {
        if (empty($texts)) {
            return [];
        }

        $options = [
            'target' => $this->targetLocale($targetLocale),
            'format' => 'html',
        ];

        if ($sourceLocale) {
            $options['source'] = $sourceLocale;
        }

        $results = $this->getClient()->translateBatch(array_values($texts), $options);

        return array_map(function ($result) {
            return html_entity_decode($result['text']);
        }, $results);
    }
}
