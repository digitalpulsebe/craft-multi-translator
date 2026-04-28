<?php

namespace digitalpulsebe\craftmultitranslator\services;

use craft\helpers\App;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use GuzzleHttp\Client;

class OpenAiService extends ApiService
{
    protected ?Client $_client = null;

    public function getName(): string
    {
        $baseUrl = $this->getBaseUrl();
        if (!str_contains($baseUrl, 'api.openai.com')) {
            $host = parse_url($baseUrl, PHP_URL_HOST);
            return 'OpenAI Compatible' . ($host ? " ($host)" : '');
        }
        return 'ChatGPT (Open AI)';
    }

    public function isConnected(): bool
    {
        try {
            return $this->getClient()->get($this->getBaseUrl() . '/models')->getStatusCode() == 200;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function getClient()
    {
        if (!$this->_client) {
            $apiKey = App::parseEnv($this->getProviderSettings()->getOpenAiKey());
            $this->_client = new Client([
                'headers' => [
                    'Authorization' => "Bearer $apiKey",
                    'Content-Type' => "application/json",
                ],
				'http_errors' => true
            ]);
        }

        return $this->_client;
    }

    public function translate(string $sourceLocale = null, string $targetLocale = null, string $text = null): ?string
    {
        if (empty($text)) {
            return null;
        }

        $sourceLanguage = $this->getLanguage($sourceLocale);
        $targetLanguage = $this->getLanguage($targetLocale);

        $prompt = $this->getProviderSettings()->getOpenAiPrompt();
        $prompt = empty($prompt) ? 'Translate the following text from {source} to {target}, keep html and only answer with the translated text, if you can not translate it, just return the text i\'ve provided you: {text}' : $prompt;
        $prompt = str_replace([
            '{source}', '{target}', '{text}'
        ],[
            $sourceLanguage ?? '[guess the language]', $targetLanguage, $text
        ], $prompt);

        $model = App::parseEnv($this->getProviderSettings()->getOpenAiModel());

        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => floatval($this->getProviderSettings()->getOpenAiTemperature()),
        ];

        try {
            $response = $this->getClient()->post($this->getBaseUrl() . '/chat/completions', ['json' => $body]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'no response body';
            MultiTranslator::error('OpenAI API error: ' . $responseBody);
            throw $e;
        }

        if ($response->getStatusCode() < 300) {

            $contents = $response->getBody()->getContents();
            $contents = json_decode($contents);

            foreach ($contents->choices as $choice) {
                return $choice->message->content;
            }
        }

        return null;
    }

    /**
     * @return string|null full language name for given locale
     */
    public function getLanguage(string $locale = null): ?string
    {
        if (empty($locale)) {
            return null;
        }
        return locale_get_display_name($locale, 'en');
    }

    /**
     * Get the base URL for the OpenAI-compatible API, with env var support.
     */
    private function getBaseUrl(): string
    {
        return App::parseEnv($this->getProviderSettings()->getOpenAiBaseUrl());
    }
}
