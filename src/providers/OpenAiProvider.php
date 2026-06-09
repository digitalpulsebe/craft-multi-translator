<?php

namespace digitalpulsebe\craftmultitranslator\providers;

use craft\helpers\App;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use GuzzleHttp\Client;

class OpenAiProvider extends Provider
{
    protected ?Client $_client = null;

    public static function getHandle(): string
    {
        return 'openai';
    }

    public static function getDisplayName(): string
    {
        return 'ChatGPT (Open AI)';
    }

    /**
     * Override to include the custom base URL host when applicable.
     */
    public function getName(): string
    {
        $baseUrl = $this->getBaseUrl();
        if (!str_contains($baseUrl, 'api.openai.com')) {
            $host = parse_url($baseUrl, PHP_URL_HOST);
            return 'OpenAI Compatible' . ($host ? " ($host)" : '');
        }
        return static::getDisplayName();
    }

    public function getSettingsTemplatePath(): ?string
    {
        return 'multi-translator/_providers/openai/_settings';
    }

    public function isConnected(): bool
    {
        try {
            return $this->getClient()->get($this->getBaseUrl() . '/models')->getStatusCode() == 200;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function getClient(): Client
    {
        if (!$this->_client) {
            $apiKey = App::parseEnv($this->getSetting('openAiKey', ''));
            $this->_client = new Client([
                'headers' => [
                    'Authorization' => "Bearer $apiKey",
                    'Content-Type' => 'application/json',
                ],
                'http_errors' => true,
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

        $prompt = $this->getSetting('openAiPrompt', '');
        $prompt = empty($prompt)
            ? 'Translate the following text from {source} to {target}, keep html and only answer with the translated text, if you can not translate it, just return the text i\'ve provided you: {text}'
            : $prompt;
        $prompt = str_replace(
            ['{source}', '{target}', '{text}'],
            [$sourceLanguage ?? '[guess the language]', $targetLanguage, $text],
            $prompt
        );

        $model = App::parseEnv($this->getModel());

        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => floatval($this->getSetting('openAiTemperature', 0.5)),
        ];

        try {
            $response = $this->getClient()->post($this->getBaseUrl() . '/chat/completions', ['json' => $body]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'no response body';
            MultiTranslator::error('OpenAI API error: ' . $responseBody);
            throw $e;
        }

        if ($response->getStatusCode() < 300) {
            $contents = json_decode($response->getBody()->getContents());

            foreach ($contents->choices as $choice) {
                return $choice->message->content;
            }
        }

        return null;
    }

    /**
     * Return the full language name for a given locale string.
     */
    public function getLanguage(?string $locale): ?string
    {
        if (empty($locale)) {
            return null;
        }
        return locale_get_display_name($locale, 'en');
    }

    /**
     * Resolve the active model name, handling the 'custom' dropdown sentinel.
     */
    public function getModel(): string
    {
        $dropdown = $this->getSetting('openAiModel', 'gpt-4o');
        if ($dropdown === 'custom') {
            $custom = $this->getSetting('openAiCustomModel', '');
            return !empty($custom) ? $custom : 'gpt-4o';
        }
        return $dropdown;
    }

    /**
     * Get the base URL for the OpenAI-compatible API, with env var support.
     */
    private function getBaseUrl(): string
    {
        $baseUrl = App::parseEnv($this->getSetting('openAiBaseUrl', ''));
        return !empty($baseUrl) ? rtrim($baseUrl, '/') : 'https://api.openai.com/v1';
    }
}
