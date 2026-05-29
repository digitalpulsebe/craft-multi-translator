<?php

namespace digitalpulsebe\craftmultitranslator\providers;

use craft\base\Component;
use digitalpulsebe\craftmultitranslator\interfaces\TranslateApiService;

abstract class Provider extends Component implements TranslateApiService
{
    /**
     * This provider's own settings, injected at instantiation time.
     * Providers must only read their own settings via getSetting().
     */
    public array $settings = [];

    // =========================================================================
    // Abstract interface
    // =========================================================================

    /**
     * Machine-readable handle that identifies this provider (e.g. 'deepl').
     */
    abstract public static function getHandle(): string;

    /**
     * Human-readable display name shown in the settings UI (e.g. 'DeepL').
     */
    abstract public static function getDisplayName(): string;

    abstract public function isConnected(): bool;

    /**
     * Return the Twig template path for this provider's settings fieldset, or null if
     * the provider has no configurable settings.
     * The template is included inside {% namespace 'providers' %}{% namespace handle %}
     * so field names are namespaced automatically — use plain names only.
     */
    abstract public function getSettingsTemplatePath(): ?string;



    // =========================================================================
    // Settings access
    // =========================================================================

    /**
     * Read a value from this provider's own settings slice.
     */
    protected function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Return the variables passed to the settings template.
     * Override to add extra variables.
     */
    public function getSettingsTemplateVariables(): array
    {
        return ['settings' => $this->settings];
    }

    // =========================================================================
    // Locale helpers
    // =========================================================================

    public function sourceLocale(?string $raw): ?string
    {
        if (!empty($raw)) {
            return substr($raw, 0, 2);
        }

        return null;
    }

    public function targetLocale(string $raw): string
    {
        if (in_array($raw, ['en-GB', 'en-US'])) {
            return $raw;
        }

        $locale = substr($raw, 0, 2);

        if ($locale === 'en') {
            return $this->getSetting('defaultEnglish', 'en-US');
        }

        return $locale;
    }
}
