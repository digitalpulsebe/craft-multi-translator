<?php

namespace digitalpulsebe\craftmultitranslator\services;

use craft\base\Component;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\interfaces\TranslateApiService;

abstract class ApiService extends Component implements TranslateApiService
{
    public abstract function getName(): string;
    public abstract function isConnected(): bool;

    public function sourceLocale($raw): ?string
    {
        if (!empty($raw)) {
            $locale = substr($raw, 0, 2);
        }

        return $locale;
    }

    public function targetLocale($raw): string
    {
        if (in_array($raw, ['en-GB', 'en-US'])) {
            return $raw;
        }

        $locale = substr($raw, 0, 2);

        if ($locale == 'en') {
            return $this->getProviderSettings()->getDefaultEnglish();
        }

        return $locale;
    }

    protected function getProviderSettings(): \digitalpulsebe\craftmultitranslator\records\ProviderSettings
    {
        return MultiTranslator::getInstance()->settingsService->getProviderSettings();
    }
}
