<?php

namespace digitalpulsebe\craftmultitranslator\services;

use craft\helpers\App;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use Google\Cloud\Translate\V2\TranslateClient;

class GoogleService extends ApiService
{

    protected ?TranslateClient $_client = null;

    public function getName(): string
    {
        return 'Google Translate';
    }

    public function isConnected(): bool
    {
        try {
            return $this->translate('en', 'nl', 'test') !== null;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function getClient()
    {
        if (!$this->_client) {
            $apiKey = App::parseEnv($this->getProviderSettings()->getGoogleApiKey());
            $this->_client = new TranslateClient([
                'key' => $apiKey
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
