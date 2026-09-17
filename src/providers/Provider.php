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
    // Native array translation
    // =========================================================================

    /**
     * Whether this provider can translate several independent strings in a single
     * request via the underlying API's own array/list parameter, rather than requiring
     * them to be concatenated into one delimited document.
     * Providers that override this to return true must also override translateArray().
     */
    public function supportsNativeArrayTranslation(): bool
    {
        return false;
    }

    /**
     * Translate several independent strings in a single request. Only called when
     * supportsNativeArrayTranslation() returns true.
     * @param string[] $texts
     * @return string[] translated strings, in the same order as $texts
     */
    public function translateArray(string $sourceLocale = null, string $targetLocale = null, array $texts = []): array
    {
        throw new \LogicException(static::class . ' must override translateArray() to support native array translation.');
    }

    /**
     * The maximum number of strings this provider accepts in a single translateArray()
     * call, or null if the underlying API documents no such limit.
     */
    public function getMaxArrayChunkItems(): ?int
    {
        return null;
    }

    /**
     * The maximum total character length (measured as strlen(), i.e. bytes) of all
     * strings combined in a single translateArray() call.
     */
    public function getMaxArrayChunkChars(): int
    {
        return 20000;
    }

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
