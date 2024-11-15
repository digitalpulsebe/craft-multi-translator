<?php

namespace digitalpulsebe\craftmultitranslator\services;

use craft\base\Component;
use digitalpulsebe\craftmultitranslator\records\ProviderSettings;

class SettingsService extends Component
{
    protected ?ProviderSettings $_providerSettings = null;

    public function getProviderSettings(): ProviderSettings
    {
        if (!$this->_providerSettings) {
            $this->_providerSettings = ProviderSettings::find()->one();
        }

        if (!$this->_providerSettings) {
            // in case row is missing
            $this->_providerSettings = new ProviderSettings();
        }

        return $this->_providerSettings;
    }
}
