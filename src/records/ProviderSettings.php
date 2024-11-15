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

    public function getDeeplApiKey(): string
    {
        return $this->getSetting('deeplApiKey', '');
    }

    /**
     * controls whether translations should lean toward informal or formal language. This option is only available for some target languages
     * https://github.com/DeepLcom/deepl-php#text-translation-options
     * @return string
     */
    public function getDeeplFormality(): string
    {
        return $this->getSetting('deeplFormality', 'default');
    }

    /**
     * controls automatic-formatting-correction. Set to true to prevent automatic-correction of formatting, default: false.
     * https://github.com/DeepLcom/deepl-php#text-translation-options
     * @return bool
     */
    public function getDeeplPreserveFormatting(): bool
    {
        return $this->getSetting('deeplPreserveFormatting', false);
    }

    /**
     * default English region for non-regional English
     * @return string
     */
    public function getDefaultEnglish(): string
    {
        return $this->getSetting('defaultEnglish','en-US');
    }

    /**
     * when enabled, we don't send the source language to the api
     * @return bool
     */
    public function getDetectSourceLanguage(): bool
    {
        return $this->getSetting('detectSourceLanguage', false);
    }

    public function getGoogleApiKey(): string
    {
        return $this->getSetting('googleApiKey', '');
    }

    public function getOpenAiKey(): string
    {
        return $this->getSetting('openAiKey', '');
    }

    /**
     * Model for the OpenAI API
     * read more: https://platform.openai.com/docs/models/model-endpoint-compatibility
     * @return string
     */
    public function getOpenAiModel(): string
    {
        return $this->getSetting('openAiModel', 'gpt-4o');
    }

    /**
     * Temperature setting for the OpenAI API
     * read more: https://platform.openai.com/docs/api-reference/chat/create#chat-create-temperature
     * @return float
     */
    public function getOpenAiTemperature(): float
    {
        return floatval($this->getSetting('openAiTemperature', 0.5));
    }

    /**
     * clear the slug when setting a translated title
     * @return bool
     */
    public function getResetSlug(): bool
    {
        return $this->getSetting('resetSlug', false);
    }

    /**
     * @return string provider google|deepl|openai
     */
    public function getTranslationProvider(): string
    {
        return $this->getSetting('translationProvider', 'deepl');
    }

    /**
     * Find and update internal links inside CKeditor value
     * @return bool
     */
    public function getUpdateInternalLinks(): bool
    {
        return $this->getSetting('updateInternalLinks', true);
    }

    /**
     * Save translated result always as a Draft
     * @return bool
     */
    public function getSaveAsDraft(): bool
    {
        return $this->getSetting('saveAsDraft', false);
    }

    public function getSetting($key, $default = null): mixed
    {
        return ArrayHelper::getValue($this->settings, $key, $default);
    }

    public function asArrayForLogs(): array
    {
        $settings = $this->settings ?? [];
        $keysToRemove = ['deeplApiKey', 'googleApiKey', 'openAiKey'];
        foreach ($keysToRemove as $key) {
            if (array_key_exists($key, $settings)) {
                unset($settings[$key]);
            }
        }
        return $settings;
    }
}
