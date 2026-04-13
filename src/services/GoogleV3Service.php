<?php

namespace digitalpulsebe\craftmultitranslator\services;

use craft\helpers\App;
use Google\Cloud\Translate\V3\Client\TranslationServiceClient;
use Google\Cloud\Translate\V3\ListModelsRequest;
use Google\Cloud\Translate\V3\TranslateTextRequest;

class GoogleV3Service extends ApiService
{

    protected ?TranslationServiceClient $_client = null;
    protected ?string $_parent = null;

    public function getName(): string
    {
        return 'Google Translate V3';
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
        if ($this->_client !== null) {
            return $this->_client;
        }

        $credentialsPath = App::parseEnv($this->getProviderSettings()->getGoogleServiceAccountFilePath());

        if ($credentialsPath) {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
        } else {
            $credentials = json_decode($this->getProviderSettings()->getGoogleServiceAccount(), true);
        }

        if (empty($credentials)) {
            throw new \Exception('Google Service Account credentials are invalid.');
        }

        if (empty($credentials['project_id'])) {
            throw new \Exception('Project ID missing in Google Service Account credentials.');
        }

        if (!$this->_client) {
            $this->_client = new TranslationServiceClient([
                'credentials' => $credentials,
            ]);
        }

        $this->_parent = $this->_client->locationName(
            $credentials['project_id'],
            empty($this->getProviderSettings()->getGoogleLocation()) ? 'global' : $this->getProviderSettings()->getGoogleLocation()
        );

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
            'source_language_code' => $sourceLocale ?: null,
            'target_language_code' => $this->targetLocale($targetLocale),
        ];

        if ($this->getProviderSettings()->getGoogleModel()) {
            $requestParams['model'] = $this->getProviderSettings()->getGoogleModel();
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
