<?php
namespace digitalpulsebe\craftmultitranslator\variables;

use craft\helpers\ElementHelper;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\models\Settings;
use digitalpulsebe\craftmultitranslator\providers\Provider;
use digitalpulsebe\craftmultitranslator\records\Glossary;
use digitalpulsebe\craftmultitranslator\records\ProviderSettings;

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

    public function getProvider(): ?Provider
    {
        return MultiTranslator::getInstance()->translate->getApiProvider();
    }

    /**
     * Return all registered translation provider instances keyed by handle.
     * Used in settings.twig as `craft.multiTranslator.apiServices`.
     *
     * @return Provider[] keyed by handle string
     */
    public function getApiServices(): array
    {
        return MultiTranslator::getInstance()->translate->getApiProviders();
    }

    public function getGlossaries(): array
    {
        return Glossary::find()->all();
    }

    public function getElementHelper()
    {
        return new ElementHelper();
    }
}
