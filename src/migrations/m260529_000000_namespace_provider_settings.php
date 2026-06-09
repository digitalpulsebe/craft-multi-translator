<?php

namespace digitalpulsebe\craftmultitranslator\migrations;

use craft\db\Migration;
use digitalpulsebe\craftmultitranslator\records\ProviderSettings;

/**
 * m260529_000000_namespace_provider_settings migration.
 *
 * Reshapes provider settings into a single 'providers' wrapper key.
 *
 * Target structure:
 * {
 *   "translationProvider": "deepl",
 *   ...general options...
 *   "providers": {
 *     "deepl":     { "deeplApiKey": "...", "deeplFormality": "...", ... },
 *     "google":    { "googleApiKey": "..." },
 *     "google-v3": { "googleServiceAccountFilePath": null, ... },
 *     "openai":    { "openAiKey": "...", "openAiModel": "gpt-4o", ... }
 *   }
 * }
 *
 * Assumes a fresh install — no backward-compatibility path for old flat or
 * intermediate namespaced structures is provided.
 */
class m260529_000000_namespace_provider_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $record = ProviderSettings::find()->one();

        if (!$record) {
            return true;
        }

        $settings = is_array($record->settings) ? $record->settings : [];

        // Idempotency: already migrated
        if (isset($settings['providers']) && is_array($settings['providers'])) {
            return true;
        }

        // Map of provider handle → keys that belong to it.
        // This handles both the old flat structure and the intermediate
        // per-handle namespaced structure from the previous migration attempt.
        $providerKeyMap = [
            'deepl' => [
                'deeplApiKey',
                'deeplModelType',
                'deeplFormality',
                'deeplPreserveFormatting',
                'defaultEnglish',
            ],
            'google' => [
                'googleApiKey',
            ],
            'google-v3' => [
                'googleServiceAccountFilePath',
                'googleServiceAccount',
                'googleLocation',
                'googleModel',
            ],
            'openai' => [
                'openAiKey',
                'openAiBaseUrl',
                'openAiModel',
                'openAiCustomModel',
                'openAiPrompt',
                'openAiTemperature',
            ],
        ];

        $providers = [];

        foreach ($providerKeyMap as $handle => $keys) {
            $providerSettings = [];

            // Check intermediate namespaced structure first (handle as top-level key)
            if (isset($settings[$handle]) && is_array($settings[$handle])) {
                $providerSettings = $settings[$handle];
                unset($settings[$handle]);
            } else {
                // Fall back to flat keys at root level
                foreach ($keys as $key) {
                    if (array_key_exists($key, $settings)) {
                        $providerSettings[$key] = $settings[$key];
                        unset($settings[$key]);
                    }
                }
            }

            $providers[$handle] = $providerSettings;
        }

        $settings['providers'] = $providers;

        $record->settings = $settings;
        $record->save(false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $record = ProviderSettings::find()->one();

        if (!$record) {
            return true;
        }

        $settings = is_array($record->settings) ? $record->settings : [];

        if (!isset($settings['providers']) || !is_array($settings['providers'])) {
            return true;
        }

        // Flatten providers back to root level
        foreach ($settings['providers'] as $providerSettings) {
            if (is_array($providerSettings)) {
                foreach ($providerSettings as $key => $value) {
                    $settings[$key] = $value;
                }
            }
        }

        unset($settings['providers']);

        $record->settings = $settings;
        $record->save(false);

        return true;
    }
}
