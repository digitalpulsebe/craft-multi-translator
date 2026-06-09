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
}
