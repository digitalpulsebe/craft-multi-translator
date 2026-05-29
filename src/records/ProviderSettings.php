<?php

namespace digitalpulsebe\craftmultitranslator\records;

use craft\db\ActiveRecord;
use craft\helpers\ArrayHelper;
use yii\db\Exception;

/**
 * @property int $id
 * @property array $settings
 */
class ProviderSettings extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%multitranslator_provider_settings}}';
    }

    /**
     * @throws Exception
     */
    public static function createOrUpdate(array $settings): bool
    {
        $item = self::find()->one();

        if (empty($item)) {
            $item = new self();
        }

        $item->settings = $settings;

        return $item->save();
    }

    // =========================================================================
    // General options
    // =========================================================================

    /**
     * @return string provider handle, e.g. 'deepl'
     */
    public function getTranslationProvider(): string
    {
        return $this->getSetting('translationProvider', 'deepl');
    }

    /**
     * When enabled, we don't send the source language to the API.
     */
    public function getDetectSourceLanguage(): bool
    {
        return (bool) $this->getSetting('detectSourceLanguage', false);
    }

    /**
     * Select direction 'to target' or 'from source' in the sidebar actions.
     */
    public function getTranslationDirectionButtons(): string
    {
        return $this->getSetting('translationDirectionButtons', 'fromThis');
    }

    /**
     * Clear the slug when setting a translated title.
     */
    public function getResetSlug(): bool
    {
        return (bool) $this->getSetting('resetSlug', false);
    }

    /**
     * Find and update internal links inside CKeditor values.
     */
    public function getUpdateInternalLinks(): bool
    {
        return (bool) $this->getSetting('updateInternalLinks', true);
    }

    /**
     * Translate nested Entries inside CKeditor values.
     */
    public function getProcessNestedEntries(): bool
    {
        return (bool) $this->getSetting('processNestedEntries', true);
    }

    /**
     * Save translated result always as a Draft.
     */
    public function getSaveAsDraft(): bool
    {
        return (bool) $this->getSetting('saveAsDraft', false);
    }

    /**
     * Ignore these field handles during translation.
     *
     * @return array<int, array{handle: string}|string>
     */
    public function getDisabledFields(): array
    {
        $value = $this->getSetting('disabledFields', []);
        return is_array($value) ? $value : [];
    }

    /**
     * @return string[] of handles
     */
    public function getDisabledFieldHandles(): array
    {
        $returnHandles = [];

        foreach ($this->getDisabledFields() as $item) {
            if (is_array($item) && isset($item['handle'])) {
                $returnHandles[] = $item['handle'];
            } elseif (is_string($item)) {
                $returnHandles[] = $item;
            }
        }

        return $returnHandles;
    }

    /**
     * Query and translate disabled variants of Commerce Products.
     */
    public function getTranslateDisabledVariants(): bool
    {
        return (bool) $this->getSetting('translateDisabledVariants', false);
    }

    /**
     * Query and translate disabled Matrix Elements inside fields.
     */
    public function getTranslateDisabledMatrixElements(): bool
    {
        return (bool) $this->getSetting('translateDisabledMatrixElements', false);
    }

    // =========================================================================
    // Provider settings
    // =========================================================================

    /**
     * Return the settings array for a specific provider.
     * Providers call this via TranslateService when they are instantiated.
     */
    public function getProviderSettings(string $handle): array
    {
        $providers = $this->getSetting('providers', []);
        return is_array($providers[$handle] ?? null) ? $providers[$handle] : [];
    }

    // =========================================================================
    // Generic read / utility
    // =========================================================================

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return ArrayHelper::getValue($this->settings, $key, $default);
    }

    public function asArrayForLogs(): array
    {
        return $this->scrubSensitive($this->settings ?? []);
    }

    /**
     * Recursively remove any key whose name contains a sensitive word,
     * regardless of nesting depth or which provider added it.
     */
    private function scrubSensitive(array $data): array
    {
        $sensitivePatterns = ['/key/i', '/secret/i', '/token/i', '/password/i', '/credential/i', '/account/i'];

        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->scrubSensitive($v);
            } elseif (is_string($k)) {
                foreach ($sensitivePatterns as $pattern) {
                    if (preg_match($pattern, $k)) {
                        unset($data[$k]);
                        break;
                    }
                }
            }
        }

        return $data;
    }

    public function overrideWithConfig(array $config): void
    {
        $this->settings = array_merge($this->settings ?? [], $config);
    }
}
