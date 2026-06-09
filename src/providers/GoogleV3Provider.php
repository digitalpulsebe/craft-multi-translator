<?php

namespace digitalpulsebe\craftmultitranslator\providers;

use craft\helpers\App;
use Google\Cloud\Translate\V3\Client\TranslationServiceClient;
use Google\Cloud\Translate\V3\TranslateTextRequest;

class GoogleV3Provider extends Provider
{
    protected ?TranslationServiceClient $_client = null;
    protected ?string $_parent = null;

    public static function getHandle(): string
    {
        return 'google-v3';
    }

    public static function getDisplayName(): string
    {
        return 'Google Translate V3';
    }

    public function getSettingsTemplatePath(): ?string
    {
        return 'multi-translator/_providers/google-v3/_settings';
    }

    public function isConnected(): bool
    {
        try {
            return $this->translate('en', 'nl', 'test') !== null;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function getClient(): TranslationServiceClient
    {
        if ($this->_client !== null) {
            return $this->_client;
        }

        $credentialsPath = App::parseEnv($this->getSetting('googleServiceAccountFilePath', ''));

        if ($credentialsPath) {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
        } else {
            $credentials = json_decode($this->getSetting('googleServiceAccount', ''), true);
        }

        if (empty($credentials)) {
            throw new \Exception('Google Service Account credentials are invalid.');
        }

        if (empty($credentials['project_id'])) {
            throw new \Exception('Project ID missing in Google Service Account credentials.');
        }

        $this->_client = new TranslationServiceClient([
            'credentials' => $credentials,
        ]);

        $location = $this->getSetting('googleLocation', '') ?: 'global';
        $this->_parent = $this->_client->locationName($credentials['project_id'], $location);

        return $this->_client;
    }

    public function translate(string $sourceLocale = null, string $targetLocale = null, string $text = null): ?string
    {
        if (!$text) {
            return null;
        }

        $client = $this->getClient();

        $requestParams = [
            'parent' => $this->_parent,
            'contents' => [$text],
            'mime_type' => 'text/plain',
            'source_language_code' => $sourceLocale ?: null,
            'target_language_code' => $this->targetLocale($targetLocale),
        ];

        $model = $this->getSetting('googleModel', '');
        if ($model) {
            $requestParams['model'] = $model;
        }

        $request = new TranslateTextRequest($requestParams);

        $response = $client->translateText($request);

        $translations = $response->getTranslations();

        if (!empty($translations)) {
            return html_entity_decode($translations[0]->getTranslatedText());
        }

        return null;
    }
}
