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
        return 'ChatGPT (Open AI)';
    }

    public function isConnected(): bool
    {
        try {
            return $this->getClient()->get('https://api.openai.com/v1/models')->getStatusCode() == 200;
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
				'http_errors' => true,
                'timeout' => 30
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

        $prompt = ($sourceLanguage) ? "Translate the following text from $sourceLanguage " : 'Translate the following text ';
        $prompt .= "to $targetLanguage, keep html and only answer with the translated text, if you can not translate it, just return the text i've provided you: " . $text;

        $body = [
            'model' => $this->getProviderSettings()->getOpenAiModel(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => floatval($this->getProviderSettings()->getOpenAiTemperature()),
        ];

        $response = $this->getClient()->post('https://api.openai.com/v1/chat/completions', ['json' => $body]);

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
        return locale_get_display_language($locale, 'en');
    }
}
