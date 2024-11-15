<?php
namespace digitalpulsebe\craftmultitranslator\variables;

use DeepL\Translator;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\models\Settings;
use digitalpulsebe\craftmultitranslator\records\Glossary;
use digitalpulsebe\craftmultitranslator\records\ProviderSettings;
use digitalpulsebe\craftmultitranslator\services\ApiService;

class Variable
{
    public function getSettings(): Settings
    {
        return MultiTranslator::getInstance()->getSettings();
    }

    public function getProviderSettings(): ProviderSettings
    {
        return MultiTranslator::getInstance()->settingsService->getProviderSettings();
    }

    public function getService(): ApiService
    {
        return MultiTranslator::getInstance()->translate->getApiService();
    }

    public function getGlossaries(): array
    {
        return Glossary::find()->all();
    }
}
