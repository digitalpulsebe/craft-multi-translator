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

        $prompt = $this->buildPrompt($sourceLocale, $targetLocale, $text);

        if ($this->shouldUseResponsesApi()) {
            return $this->translateWithResponsesApi($prompt);
        }

        return $this->translateWithChatCompletions($prompt);
    }

    /**
     * Assemble the translation prompt from the configured (or default) template,
     * interpolating the {source}, {target} and {text} tokens.
     */
    protected function buildPrompt(?string $sourceLocale, ?string $targetLocale, string $text): string
    {
        $sourceLanguage = $this->getLanguage($sourceLocale);
        $targetLanguage = $this->getLanguage($targetLocale);

        $prompt = $this->getSetting('openAiPrompt', '');
        $prompt = empty($prompt)
            ? 'Translate the following text from {source} to {target}, keep html and only answer with the translated text, if you can not translate it, just return the text i\'ve provided you: {text}'
            : $prompt;

        return str_replace(
            ['{source}', '{target}', '{text}'],
            [$sourceLanguage ?? '[guess the language]', $targetLanguage, $text],
            $prompt
        );
    }

    /**
     * Legacy Chat Completions path, used for all OpenAI-compatible providers and
     * whenever no Vector Store is configured.
     */
    protected function translateWithChatCompletions(string $prompt): ?string
    {
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

        $response = $this->postJson('/chat/completions', $body);

        if ($response && $response->getStatusCode() < 300) {
            $contents = json_decode($response->getBody()->getContents());

            foreach ($contents->choices as $choice) {
                return $choice->message->content;
            }
        }

        return null;
    }

    /**
     * OpenAI Responses API path with File Search over a Vector Store.
     * The interpolated prompt is sent as the request input; File Search retrieves
     * terminology, glossaries and style guides from the configured Vector Store.
     */
    protected function translateWithResponsesApi(string $prompt): ?string
    {
        $body = [
            'model' => App::parseEnv($this->getModel()),
            'input' => $prompt,
            'temperature' => floatval($this->getSetting('openAiTemperature', 0.5)),
        ];

        // Reference a centrally managed prompt (Prompt Management) when configured.
        // The stored prompt carries the behaviour; the target language and text are
        // still supplied dynamically through the request input above.
        if (!empty($this->getPromptId())) {
            $body['prompt'] = ['id' => $this->getPromptId()];
            if (!empty($this->getPromptVersion())) {
                $body['prompt']['version'] = $this->getPromptVersion();
            }
        }

        // Enable File Search over the configured Vector Store when applicable.
        // Request-level tools override any tools defined on a stored prompt, so the
        // plugin's Vector Store augments the prompt's knowledge base.
        if ($this->fileSearchEnabled()) {
            $body['tools'] = [
                [
                    'type' => 'file_search',
                    'vector_store_ids' => [$this->getVectorStoreId()],
                ],
            ];
        }

        $response = $this->postJson('/responses', $body);

        if ($response && $response->getStatusCode() < 300) {
            return $this->extractResponsesOutputText($response->getBody()->getContents());
        }

        return null;
    }

    /**
     * Extract the assistant's text from a Responses API payload.
     *
     * The raw HTTP response has no top-level `output_text` helper (that is added by
     * the official SDKs only), so we walk the `output[]` array, collect every
     * `message` item's `output_text` content parts and concatenate them, skipping
     * tool-call items such as `file_search_call`.
     */
    protected function extractResponsesOutputText(string $json): ?string
    {
        $contents = json_decode($json);

        // Prefer the convenience field when a compatible endpoint does provide it.
        if (!empty($contents->output_text) && is_string($contents->output_text)) {
            return $contents->output_text;
        }

        if (empty($contents->output) || !is_array($contents->output)) {
            return null;
        }

        $text = '';
        foreach ($contents->output as $item) {
            if (($item->type ?? null) !== 'message' || empty($item->content)) {
                continue;
            }
            foreach ($item->content as $part) {
                if (($part->type ?? null) === 'output_text' && isset($part->text)) {
                    $text .= $part->text;
                }
            }
        }

        return $text !== '' ? $text : null;
    }

    /**
     * POST a JSON body to a path relative to the configured base URL, logging and
     * re-throwing any client error with the API's response body attached.
     */
    protected function postJson(string $path, array $body): ?\Psr\Http\Message\ResponseInterface
    {
        try {
            return $this->getClient()->post($this->getBaseUrl() . $path, ['json' => $body]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'no response body';
            MultiTranslator::error('OpenAI API error: ' . $responseBody);
            throw $e;
        }
    }

    /**
     * Whether the Responses API should be used for this translation.
     *
     * Prompt Management (Prompt IDs) and File Search are OpenAI-specific features,
     * so the Responses API is only used on the official api.openai.com host, and only
     * when a Prompt ID is configured or File Search is enabled.
     */
    protected function shouldUseResponsesApi(): bool
    {
        if (!str_contains($this->getBaseUrl(), 'api.openai.com')) {
            return false;
        }

        return !empty($this->getPromptId()) || $this->fileSearchEnabled();
    }

    /**
     * Whether File Search over a Vector Store should be enabled: a Vector Store ID is
     * configured and the "Enable File Search" switch is not set to off.
     */
    protected function fileSearchEnabled(): bool
    {
        return !empty($this->getVectorStoreId())
            && $this->getSetting('openAiEnableFileSearch', 'auto') !== 'off';
    }

    /**
     * Resolve the configured OpenAI Vector Store ID (with env var support).
     */
    protected function getVectorStoreId(): string
    {
        return trim(App::parseEnv($this->getSetting('openAiVectorStoreId', '')) ?? '');
    }

    /**
     * Resolve the configured OpenAI Prompt ID for Prompt Management (with env var support).
     */
    protected function getPromptId(): string
    {
        return trim(App::parseEnv($this->getSetting('openAiPromptId', '')) ?? '');
    }

    /**
     * Resolve the optional stored prompt version. Empty means OpenAI uses the
     * currently published version.
     */
    protected function getPromptVersion(): string
    {
        return trim(App::parseEnv($this->getSetting('openAiPromptVersion', '')) ?? '');
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
