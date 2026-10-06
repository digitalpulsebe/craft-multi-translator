<?php

namespace digitalpulsebe\craftmultitranslator\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property bool $enabled
 * @property string $name
 * @property string $deeplId
 * @property string $language
 * @property array $data
 */
class StyleRule extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%multitranslator_deepl_style_rules}}';
    }

    /**
     * names of the configured rule categories enabled in the DeepL account
     * @return string[]
     */
    public function getConfiguredRuleCategories(): array
    {
        return $this->getDecodedData()['configuredRules'] ?? [];
    }

    /**
     * labels of the custom instructions attached to this style rule
     * @return string[]
     */
    public function getCustomInstructionLabels(): array
    {
        return $this->getDecodedData()['customInstructions'] ?? [];
    }

    protected function getDecodedData(): array
    {
        if (is_string($this->getAttribute('data'))) {
            $data = json_decode($this->getAttribute('data'), true);
        } else {
            $data = $this->getAttribute('data');
        }

        return is_array($data) ? $data : [];
    }

    public function rules()
    {
        return [
            [['name', 'deeplId', 'language'], 'required'],
            [['name', 'deeplId', 'language'], 'trim'],
        ];
    }
}
