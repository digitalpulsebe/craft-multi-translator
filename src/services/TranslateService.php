<?php

namespace digitalpulsebe\craftmultitranslator\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\FieldInterface;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\elements\Asset;
use craft\elements\ContentBlock;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\errors\UnsupportedSiteException;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;
use digitalpulsebe\craftmultitranslator\events\FieldTranslationEvent;
use digitalpulsebe\craftmultitranslator\events\RegisterSerializersEvent;
use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use digitalpulsebe\craftmultitranslator\events\ElementTranslationEvent;
use digitalpulsebe\craftmultitranslator\helpers\SerializerHelper;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\serializers\Matrix as MatrixSerializer;
use digitalpulsebe\craftmultitranslator\serializers\Hyper as HyperSerializer;
use digitalpulsebe\craftmultitranslator\serializers\Text as TextSerializer;
use digitalpulsebe\craftmultitranslator\serializers\RichText as RichTextSerializer;
use digitalpulsebe\craftmultitranslator\serializers\Table as TableSerializer;
use digitalpulsebe\craftmultitranslator\serializers\EtherSeo as EtherSeoSerializer;
use digitalpulsebe\craftmultitranslator\serializers\Seomatic as SeomaticSerializer;
use digitalpulsebe\craftmultitranslator\serializers\Vizy as VizySerializer;
use digitalpulsebe\craftmultitranslator\serializers\ContentBlock as ContentBlockSerializer;
use digitalpulsebe\craftmultitranslator\serializers\Linkit as LinkitSerializer;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\ForbiddenHttpException;

class TranslateService extends Component
{
    public const EVENT_BEFORE_ELEMENT_TRANSLATION = 'beforeElementTranslation';
    public const EVENT_AFTER_ELEMENT_TRANSLATION = 'afterElementTranslation';
    public const EVENT_BEFORE_FIELD_TRANSLATION = 'beforeFieldTranslation';
    public const EVENT_AFTER_FIELD_TRANSLATION = 'afterFieldTranslation';
    public const EVENT_REGISTER_SERIALIZERS = 'registerTranslationSerializers';

    static array $matrixFields = [
        'craft\fields\Matrix',
        'benf\neo\Field',
        'verbb\supertable\fields\SuperTableField',
    ];

    protected array $serializers = [];

    public function init(): void
    {
        parent::init();

        $this->registerSerializerClasses();
    }

    /**
     * @param Element $source
     * @param Site $sourceSite
     * @param Site $targetSite
     * @param bool $isRootElement
     * @return Element|null
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     * @throws InvalidConfigException
     */
    public function translateElement(Element $source, Site $sourceSite, Site $targetSite, bool $isRootElement = true): ?Element
    {
        if (!$this->onBeforeElementTranslation($source, $sourceSite, $targetSite, $isRootElement)) {
            return null;
        }

        $originalHtml = SerializerHelper::serialize($this->serializeElement($source, $sourceSite, $targetSite));
        $translatedHtml = $this->translateText($sourceSite->language, $targetSite->language, $originalHtml);
        $translatedValues = SerializerHelper::unserialize($translatedHtml);

        // find or create target (destination)
        $targetElement = $this->findTargetElement($source, $targetSite->id);
        $this->setElementTranslation($source, $targetElement, $translatedValues);

        $revisionNotes = 'Translated by Multi Translator from "'
            .$sourceSite->name.'" ('.$sourceSite->getLocale()->getLanguageID().') to "'
            .$targetSite->name.'" ('.$targetSite->getLocale()->getLanguageID().').'
        ;
        $draftName = 'Translated Draft ('.$sourceSite->getLocale()->getLanguageID().'->'.$targetSite->getLocale()->getLanguageID().')';

        if (!$this->onAfterElementTranslation($source, $targetElement, $sourceSite, $targetSite, $isRootElement)) {
            return null;
        }

        if ($targetElement instanceof Entry && $targetElement->getIsDraft()) {
            // only Entries can have drafts
            \Craft::$app->drafts->saveElementAsDraft($targetElement, null, $draftName, $revisionNotes);
        } elseif ($targetElement instanceof Entry && $this->getProviderSettings()->getSaveAsDraft()) {
            // only Entries can have drafts
            $targetElement = \Craft::$app->drafts->createDraft($targetElement, null, $draftName, $revisionNotes);
            $targetElement->setFieldValues($translatedValues);
            \Craft::$app->elements->saveElement($targetElement);
        } else {
            $targetElement->setRevisionNotes($revisionNotes);
            \Craft::$app->elements->saveElement($targetElement);
        }

        if ($source instanceof Product) {
            // translate each variant too;
            // read settings to include disabled variants
            $includeDisabled = $this->getProviderSettings()->getTranslateDisabledVariants();
            foreach ($source->getVariants($includeDisabled) as $variant) {
                $this->translateElement($variant, $sourceSite, $targetSite);
            }
        }

        if (MultiTranslator::getInstance()->getSettings()->debug) {
            MultiTranslator::log([
                'settings' => MultiTranslator::getInstance()->getSettings(),
                'providerSettings' => $this->getProviderSettings()->asArrayForLogs(),
                'fields' => array_map(function (FieldInterface $field) {
                    return [
                        'handle' => $field->handle,
                        'class' => get_class($field),
                        'translationMethod' => $field->translationMethod,
                        'propagationMethod' => $field->propagationMethod ?? null,
                    ];
                }, $source->fieldLayout->getCustomFields()),
                'sourceSiteLanguage' => $sourceSite->language,
                'targetSiteLanguage' => $targetSite->language,
                'propagationMethod' => $source?->section->propagationMethod ?? null,
                'sourceEntry' => ['id' => $source->id, 'siteId' => $source->siteId, 'draft' => $source->getIsDraft(), 'customFields' => $source->getSerializedFieldValues()],
                'targetElement' => ['id' => $targetElement->id, 'siteId' => $targetElement->siteId, 'draft' => $targetElement->getIsDraft()],
                'serialized' => $originalHtml,
                'translatedValues' => $translatedValues,
            ]);
        }

        if (!empty($targetElement->errors)) {
            MultiTranslator::error([
                'message' => 'Validation errors while saving.',
                'errors' => $targetElement->errors,
                'translatedValues' => $translatedValues,
                'sourceEntry' => ['id' => $source->id, 'siteId' => $source->siteId],
                'targetElement' => ['id' => $targetElement->id, 'siteId' => $targetElement->siteId],
            ]);
        }

        return $targetElement;
    }

    /**
     * Serialize an element for translation
     * @param Element $source
     * @param Site $sourceSite
     * @param Site $targetSite
     * @return array
     * @throws InvalidConfigException
     */
    public function serializeElement(Element $source, Site $sourceSite, Site $targetSite): array
    {
        $disabledFields = $this->getProviderSettings()->getDisabledFieldHandles();

        $serialized['id'] = $source->id;

        if ($source->title && $source->getIsTitleTranslatable() && !in_array('title', $disabledFields)) {
            $serialized['title'] = $source->title;
        }

        if ($source instanceof Asset && $source->alt && !in_array($source->getVolume()->altTranslationMethod, ['none', 'custom'])) {
            // assets can have a translatable alt field
            $serialized['alt'] = $source->alt;
        }

        $serialized['fields'] = $this->serializeElementFields($source, $sourceSite, $targetSite);

        return $serialized;
    }

    /**
     * Serialize all translatable fields of an element
     * @param Element|ContentBlock $element
     * @param Site $sourceSite
     * @param Site $targetSite
     * @return array
     */
    public function serializeElementFields(Element|ContentBlock $element, Site $sourceSite, Site $targetSite): array
    {
        return ElementHelper::getElementTranslatableFields($element)
            ->filter(function ($field)  {
                return isset($this->serializers[get_class($field)]);
            })
            ->map(function ($field) use ($element, $sourceSite, $targetSite) {
                $serializer = $this->getSerializer($field);

                if ($serializer) {

                    $onBeforeFieldTranslationEvent = $this->onBeforeFieldTranslation($element, $field, $sourceSite, $targetSite);

                    if (!$onBeforeFieldTranslationEvent->isValid) {
                        return null;
                    }

                    return $serializer->serialize($element, $sourceSite, $targetSite);
                }

                return null;
            })
            ->all()
        ;
    }

    /**
     * Get the serializer for a field
     * @param FieldInterface $field
     * @return FieldSerializer|null
     */
    protected function getSerializer(FieldInterface $field): ?FieldSerializer
    {
        $fieldClass = get_class($field);
        $serializerClass = $this->serializers[$fieldClass] ?? null;

        if ($serializerClass) {
            return new $serializerClass($field);
        }

        return null;
    }

    /**
     * Set the translation values on an element
     * @param Element $source
     * @param Element $target
     * @param array|null $translatedValues
     */
    public function setElementTranslation(Element $source, Element $target, array $translatedValues = null): void
    {
        if (isset($translatedValues['title'])) {
            $target->title = $translatedValues['title'];
        }

        if (isset($translatedValues['alt'])) {
            // assets can have a translatable alt field
            $target->alt = $translatedValues['alt'];
        }

        if (isset($translatedValues['fields'])) {
            $target->setFieldValues($this->setElementFieldsTranslations($source, $target, $translatedValues['fields']));
        }
    }

    /**
     * Set the translated field values on the fields of an element
     * @param Element $source
     * @param Element $target
     * @param $translatedFieldValues
     * @return array
     */
    public function setElementFieldsTranslations(Element $source, Element $target, $translatedFieldValues): array
    {
        return ElementHelper::getElementTranslatableFields($source)
            ->filter(function ($field) use ($translatedFieldValues) {
                return !empty($translatedFieldValues[$field->handle] ?? null);
            })
            ->map(function ($field) use ($source, $target, $translatedFieldValues) {
                $serializer = $this->getSerializer($field);
                $fieldData = $translatedFieldValues[$field->handle] ?? null;
                return $serializer?->setFieldData($source, $target, $fieldData);
            })
            ->all()
        ;
    }

    /**
     * Find the target element in the target site for a source element
     * @param Element $source
     * @param int $targetSiteId
     * @return Element
     */
    public function findTargetElement(Element $source, int $targetSiteId): Element
    {
        if ($source instanceof Product) {
            return ElementHelper::one(Product::class, $source->id, $targetSiteId);
        } elseif ($source instanceof Asset) {
            return ElementHelper::one(Asset::class, $source->id, $targetSiteId);
        } elseif ($source instanceof Variant) {
            return Variant::find()->status(null)->id($source->id)->siteId($targetSiteId)->one();
        } else {
            return $this->findTargetEntry($source, $targetSiteId);
        }
    }

    /**
     * Find the target entry in the target site for a source entry
     * @param Entry $source
     * @param int $targetSiteId
     * @return Entry
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     * @throws InvalidElementException
     * @throws UnsupportedSiteException
     * @throws ForbiddenHttpException
     */
    public function findTargetEntry(Entry $source, int $targetSiteId): Entry
    {
        $targetEntry = ElementHelper::one(Entry::class, $source->id, $targetSiteId);

        if (empty($targetEntry)) {
            // we need to create one for this target site
            if ($source?->section?->propagationMethod == PropagationMethod::Custom) {
                // create for site first, but keep enabled status
                $sitesEnabled = $source->getEnabledForSite();
                if (is_array($sitesEnabled) && !isset($sitesEnabled[$targetSiteId])) {
                    $sitesEnabled[$targetSiteId] = $source->enabledForSite;
                } else {
                    $sitesEnabled = [
                        $source->site->id => $source->enabledForSite,
                        $targetSiteId => $source->enabledForSite,
                    ];
                }

                $source->setEnabledForSite($sitesEnabled);

                if ($source->getIsDraft()) {
                    Craft::$app->drafts->saveElementAsDraft($source);
                } else {
                    Craft::$app->elements->saveElement($source);
                }

                $targetEntry = ElementHelper::one(Entry::class, $source->id, $targetSiteId);
            } elseif ($source?->section?->propagationMethod == PropagationMethod::All) {
                // it should have been there, so propagate
                $targetEntry = Craft::$app->elements->propagateElement($source, $targetSiteId, false);
            } else {
                // todo find a way to duplicate drafts
                $targetEntry = Craft::$app->elements->duplicateElement($source, ['siteId' => $targetSiteId]);
            }
        }

        return $targetEntry;
    }

    /**
     * Translate text (or HTML) using the configured provider
     * @param string|null $sourceLocale
     * @param string|null $targetLocale
     * @param string|null $text
     * @return string|null
     */
    public function translateText(string $sourceLocale = null, string $targetLocale = null, string $text = null): ?string
    {
        if ($this->getProviderSettings()->getDetectSourceLanguage()) {
            $sourceLocale = null;
        }

        return $this->getApiService()->translate($sourceLocale, $targetLocale, $text);
    }

    /**
     * Get the configured translation provider API service
     * @return ApiService|null
     */
    public function getApiService(): ?ApiService
    {
        $provider = $this->getProviderSettings()->getTranslationProvider();

        if ($provider == 'google') {
            return MultiTranslator::getInstance()->google;
        } elseif ($provider == 'openai') {
            return MultiTranslator::getInstance()->openai;
        } elseif ($provider == 'deepl') {
            return MultiTranslator::getInstance()->deepl;
        }

        return null;
    }

    public function onBeforeElementTranslation(Element $source, Site $sourceSite, Site $targetSite, bool $isRootElement): bool
    {
        $event = new ElementTranslationEvent([
            'sourceElement' => $source,
            'sourceSite' => $sourceSite,
            'targetSite' => $targetSite,
            'isRootElement' => $isRootElement,
        ]);

        $this->trigger(self::EVENT_BEFORE_ELEMENT_TRANSLATION, $event);

        return $event->isValid;
    }

    public function onAfterElementTranslation(Element $source, Element $target, Site $sourceSite, Site $targetSite, bool $isRootElement = true): bool
    {
        $event = new ElementTranslationEvent([
            'sourceElement' => $source,
            'targetElement' => $target,
            'sourceSite' => $sourceSite,
            'targetSite' => $targetSite,
            'isRootElement' => $isRootElement,
        ]);

        $this->trigger(self::EVENT_AFTER_ELEMENT_TRANSLATION, $event);

        return $event->isValid;
    }

    public function onBeforeFieldTranslation(Element $source, FieldInterface $field, Site $sourceSite, Site $targetSite): FieldTranslationEvent
    {
        $event = new FieldTranslationEvent([
            'sourceElement' => $source,
            'field' => $field,
            'sourceSite' => $sourceSite,
            'targetSite' => $targetSite,
        ]);

        $this->trigger(self::EVENT_BEFORE_FIELD_TRANSLATION, $event);

        return $event;
    }

    /**
     * @param Element $source
     * @param FieldInterface $field
     * @param Site $sourceSite
     * @param Site $targetSite
     * @param mixed $translatedValue
     * @return mixed translated value, possibly overridden on event handle
     */
    public function onAfterFieldTranslation(Element $source, FieldInterface $field, Site $sourceSite, Site $targetSite, mixed $translatedValue): mixed
    {
        $event = new FieldTranslationEvent([
            'sourceElement' => $source,
            'field' => $field,
            'sourceSite' => $sourceSite,
            'targetSite' => $targetSite,
            'translatedValue' => $translatedValue,
        ]);

        $this->trigger(self::EVENT_AFTER_FIELD_TRANSLATION, $event);

        return $event->translatedValue;
    }

    protected function getProviderSettings(): \digitalpulsebe\craftmultitranslator\records\ProviderSettings
    {
        return MultiTranslator::getInstance()->settingsService->getProviderSettings();
    }

    private function registerSerializerClasses(): void
    {
        foreach (static::$matrixFields as $fieldClass) {
            $this->serializers[$fieldClass] = MatrixSerializer::class;
        }

        $this->serializers['craft\fields\PlainText'] = TextSerializer::class;
        $this->serializers['craft\redactor\Field'] = RichTextSerializer::class;
        $this->serializers['craft\ckeditor\Field'] = RichTextSerializer::class;
        $this->serializers['abmat\tinymce\Field'] = RichTextSerializer::class;
        $this->serializers['verbb\hyper\fields\HyperField'] = HyperSerializer::class;
        $this->serializers['craft\fields\Table'] = TableSerializer::class;
        $this->serializers['craft\fields\ContentBlock'] = ContentBlockSerializer::class;
        $this->serializers['presseddigital\linkit\fields\LinkitField'] = LinkitSerializer::class;
        $this->serializers['ether\seo\fields\SeoField'] = EtherSeoSerializer::class;
        $this->serializers['nystudio107\seomatic\fields\SeoSettings'] = SeomaticSerializer::class;
        $this->serializers['verbb\vizy\fields\VizyField'] = VizySerializer::class;

        // trigger event to add custom serializers
        $event = new RegisterSerializersEvent([
            'serializers' => $this->serializers,
        ]);

        $this->trigger(self::EVENT_REGISTER_SERIALIZERS, $event);

        $this->serializers = $event->serializers;
    }
}
