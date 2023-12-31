<?php
namespace digitalpulsebe\craftmultitranslator\variables;

use DeepL\Translator;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\models\Settings;

class Variable
{
    public function getUsage(): ?\DeepL\Usage
    {
        if (!empty($this->getSettings()->apiKey)) {
            try {
                return $this->getClient()->getUsage();
            } catch (\Throwable $throwable) {
                return null;
            }

        }

        return null;
    }
    public function getSupportedLanguages(): array
    {
        if (!empty($this->getSettings()->apiKey)) {
            try {
                return [
                    'source' => $this->getClient()->getSourceLanguages(),
                    'target' => $this->getClient()->getTargetLanguages(),
                ];
            } catch (\Throwable $throwable) {
                return [];
            }

        }

        return [];
    }

    public function getSettings(): Settings
    {
        return MultiTranslator::getInstance()->getSettings();
    }

    public function getClient(): Translator
    {
        return MultiTranslator::getInstance()->deepl->getClient();
    }
}
