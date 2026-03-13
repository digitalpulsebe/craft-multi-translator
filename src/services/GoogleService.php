<?php

namespace digitalpulsebe\craftmultitranslator\services;

use craft\helpers\App;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\Translate\V3\Client\TranslationServiceClient;
use Google\Cloud\Translate\V3\TranslateTextRequest;

class GoogleService extends ApiService
{

    protected ?TranslationServiceClient $_client = null;

    public function getName(): string
    {
        return 'Google Translate';
    }

    public function isConnected(): bool
    {
//        try {
            return $this->translate('en', 'nl', 'test') !== null;
//        } catch (\Throwable $exception) {
//            return false;
//        }
    }

    public function getClient()
    {
        if (!$this->_client) {
            $this->_client = new TranslationServiceClient([
                'credentials' => json_decode(file_get_contents(App::parseEnv('@storage/credentials/digital-pulse-101ace80a2fd.json')), true),
            ]);
        }

        return $this->_client;
    }

    public function translate(string $sourceLocale = null, string $targetLocale = null, string $text = null): ?string
    {
        if (!$text) {
            return null;
        }

        $client = $this->getClient();

        $parent = $client->locationName('digital-pulse', 'global');

        $request = new TranslateTextRequest([
            'parent' => $parent,
            'contents' => [$text],
            'source_language_code' => $sourceLocale ?: null,
            'target_language_code' => $this->targetLocale($targetLocale),
        ]);

        $response = $client->translateText($request);

        $translations = $response->getTranslations();

        if (!empty($translations)) {
            return html_entity_decode($translations[0]->getTranslatedText());
        }

        return null;
    }
}
