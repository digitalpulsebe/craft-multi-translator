<?php

namespace digitalpulsebe\craftmultitranslator\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fields\Table;
use craft\models\Section;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\events\FieldTranslationEvent;
use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use digitalpulsebe\craftmultitranslator\events\ElementTranslationEvent;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

class TranslateService extends Component
{
    public const EVENT_BEFORE_ELEMENT_TRANSLATION = 'beforeElementTranslation';
    public const EVENT_AFTER_ELEMENT_TRANSLATION = 'afterElementTranslation';
    public const EVENT_BEFORE_FIELD_TRANSLATION = 'beforeFieldTranslation';
    public const EVENT_AFTER_FIELD_TRANSLATION = 'afterFieldTranslation';

    static array $textFields = [
        'craft\fields\PlainText',
        'craft\redactor\Field',
        'craft\ckeditor\Field',
        'abmat\tinymce\Field',
    ];
    static array $matrixFields = [
        'craft\fields\Matrix',
        'benf\neo\Field',
        'verbb\supertable\fields\SuperTableField',
    ];

    /**
     * @param Element $source
     * @param Site $sourceSite
     * @param Site $targetSite
     * @return Element
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function translateElement(Element $source, Site $sourceSite, Site $targetSite, bool $isRootElement = true): ?Element
    {
        if (!$this->onBeforeElementTranslation($source, $sourceSite, $targetSite, $isRootElement)) {
            return null;
        }

        // translate inside of Element, get serialized data
        $translatedValues = $this->translateElementFields($source, $sourceSite, $targetSite, true);

        // find or create target (destination)
        $targetElement = $this->findTargetElement($source, $targetSite->id);

        if (isset($translatedValues['title'])) {
            // title is not a custom field
            $targetElement->title = $translatedValues['title'];

            if (MultiTranslator::getInstance()->settingsService->getProviderSettings()->getResetSlug()) {
                $targetElement->slug = null;
            }

            unset($translatedValues['title']);
        }

        if (isset($translatedValues['alt'])) {
            // alt is not a custom field
            $targetElement->alt = $translatedValues['alt'];
            unset($translatedValues['alt']);
        }

        // set field values for custom fields
        $targetElement->setFieldValues($translatedValues);

        $revisionNotes = 'Translated by Multi Translator from "'.$sourceSite->name.'" ('.$sourceSite->getLocale()->getLanguageID().')';

        if (!$this->onAfterElementTranslation($source, $targetElement, $sourceSite, $targetSite, $isRootElement)) {
            return null;
        }

        if ($targetElement instanceof Entry && $targetElement->getIsDraft()) {
            // only Entries can have drafts
            \Craft::$app->drafts->saveElementAsDraft($targetElement, null, 'Translated Draft', $revisionNotes);
        } elseif ($targetElement instanceof Entry && $this->getProviderSettings()->getSaveAsDraft()) {
            // only Entries can have drafts
            $targetElement = \Craft::$app->drafts->createDraft($targetElement, null, 'Translated Draft', $revisionNotes);
            $targetElement->setFieldValues($translatedValues);
            \Craft::$app->elements->saveElement($targetElement);
        } else {
            $targetElement->setRevisionNotes($revisionNotes);
            \Craft::$app->elements->saveElement($targetElement);
        }

        if ($source instanceof Product) {
            // translate each variant too
            foreach ($source->getVariants() as $variant) {
                $this->translateElement($variant, $sourceSite, $targetSite);
            }
        }

        if (MultiTranslator::getInstance()->getSettings()->debug) {
            MultiTranslator::log([
                'settings' => MultiTranslator::getInstance()->getSettings(),
                'providerSettings' => MultiTranslator::getInstance()->settingsService->getProviderSettings()->asArrayForLogs(),
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
     * @param Element $source
     * @param Site $sourceSite
     * @param Site $targetSite
     * @return array
     */
    public function translateElementFields(Element $source, Site $sourceSite, Site $targetSite, bool $isRootElement = false): array
    {
        $target = [];

        $disabledFields = $isRootElement ? $this->getProviderSettings()->getDisabledFieldHandles() : [];

        if ($source->title && $source->getIsTitleTranslatable() && !in_array('title', $disabledFields)) {
            $target['title'] = $this->translateText($sourceSite->language, $targetSite->language, $source->title);
        }

        if ($source instanceof Asset && $source->alt && !in_array($source->getVolume()->altTranslationMethod, ['none', 'custom'])) {
            // assets can have a translatable alt field
            $target['alt'] = $this->translateText($sourceSite->language, $targetSite->language, $source->alt);
        }

        foreach ($source->fieldLayout->getCustomFields() as $field) {
            $translatedValue = null;
            $fieldTranslatable = $field->translationMethod != Field::TRANSLATION_METHOD_NONE;
            $beforeTranslationEvent = $this->onBeforeFieldTranslation($source, $field, $sourceSite, $targetSite);
            $processField = boolval($fieldTranslatable); // if translatable

            if (in_array($field->handle, $disabledFields)) {
                continue;
            }

            if (!$beforeTranslationEvent->isValid) {
                // translated value, possibly overridden on event handle
                $translatedValue = $beforeTranslationEvent->translatedValue;
            } elseif (in_array(get_class($field), static::$textFields) && $processField) {
                // normal text fields
                $translatedValue = $this->translateTextField($source, $field, $sourceSite, $targetSite);
            } elseif (in_array(get_class($field), static::$matrixFields)) {
                // dig deeper in Matrix fields
                $translatedValue = $this->translateMatrixField($source, $field, $sourceSite, $targetSite);
            } elseif (get_class($field) == Table::class && $processField) {
                // loop over table
                $translatedValue = $this->translateTable($source, $field, $sourceSite, $targetSite);
            } elseif (get_class($field) == 'lenz\linkfield\fields\LinkField' && $processField) {
                // translate linkfield custom label
                $translatedValue = $this->translateLinkField($source, $field, $sourceSite, $targetSite);
            } elseif (get_class($field) == 'presseddigital\linkit\fields\LinkitField' && $processField) {
                // translate Linkit custom text
                $translatedValue = $this->translateLinkitField($source, $field, $sourceSite, $targetSite);
            } elseif (get_class($field) == 'verbb\hyper\fields\HyperField' && $processField) {
                // translate hyper linkText
                $translatedValue = $this->translateHyperField($source, $field, $sourceSite, $targetSite);
            } elseif (get_class($field) == 'ether\seo\fields\SeoField' && $processField) {
                // translate Ether Seo title and description
                $translatedValue = $this->translateEtherSeoField($source, $field, $sourceSite, $targetSite);
            } elseif (get_class($field) == 'nystudio107\seomatic\fields\SeoSettings' && $processField) {
                // translate nystudio107's Seomatic data
                $translatedValue = $this->translateSeomaticField($source, $field, $sourceSite, $targetSite);
            } elseif (get_class($field) == 'verbb\vizy\fields\VizyField' && $processField) {
                // translate nystudio107's Seomatic data
                $translatedValue = $this->translateVizyField($source, $field, $sourceSite, $targetSite);
            }

            if (in_array(get_class($field), [
                'craft\ckeditor\Field',
                'abmat\tinymce\Field',
            ])) {
                // search for interal href links
                $translatedValue = $this->translateLinks($translatedValue, $sourceSite, $targetSite);
                $translatedValue = $this->translateNestedEntries($translatedValue, $sourceSite, $targetSite);
            }

            $translatedValue = $this->onAfterFieldTranslation($source, $field, $sourceSite, $targetSite, $translatedValue);

            if ($translatedValue) {
                $target[$field->handle] = $translatedValue;
            } else {
                $target[$field->handle] = $field->serializeValue($source->getFieldValue($field->handle), $source);
            }
        }

        return $target;
    }

    public function translateTextField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): ?string
    {
        $value = $field->serializeValue($element->getFieldValue($field->handle), $element);

        return $this->translateText($sourceSite->language, $targetSite->language, $value);
    }

    public function translateTable(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): array
    {
        $sourceData = $field->serializeValue($element->getFieldValue($field->handle), $element);

        $targetData = [];

        if (is_array($sourceData)) {
            $textColumns = [];
            foreach ($field->columns as $columnName => $columnConfig) {
                if (in_array($columnConfig['type'], ['singleline', 'multiline', 'heading'])) {
                    // only process types with text
                    $textColumns[] = $columnName;
                }
            }

            foreach ($sourceData as $sourceRow) {
                $targetRow = [];
                foreach ($sourceRow as $columnName => $value) {
                    if (in_array($columnName, $textColumns)) {
                        $targetRow[$columnName] = $this->translateText($sourceSite->language, $targetSite->language, $value);
                    } else {
                        $targetRow[$columnName] = $value;
                    }
                }
                $targetData[] = $targetRow;
            }
        }

        return $targetData;
    }

    public function translateMatrixField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): array
    {
        $query = $element->getFieldValue($field->handle);

        // serialize current value
        $serialized = $element->getSerializedFieldValues([$field->handle])[$field->handle];

        foreach ($query->all() as $matrixElement) {
            $translatedMatrixValues = $this->translateElementFields($matrixElement, $sourceSite, $targetSite);
            foreach ($translatedMatrixValues as $matrixFieldHandle => $value) {
                // only set translated values in matrix array
                if ($value && isset($serialized[$matrixElement->id])) {
                    if ($matrixFieldHandle == 'title') {
                        $serialized[$matrixElement->id][$matrixFieldHandle] = $value;
                    } else {
                        $serialized[$matrixElement->id]['fields'][$matrixFieldHandle] = $value;
                    }
                }
            }
        }

        if (get_class($field) == 'benf\neo\Field' && $field->propagationMethod != PropagationMethod::None) {
            // special case to avoid neo overwriting blocks in all languages
            return ['blocks' => $serialized];
        }

        return $serialized;
    }

    public function translateLinkField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): ?array
    {
        $value = $element->getFieldValue($field->handle);
        if ($value) {
            try {
                $array = $value->toArray();

                if (!empty($array['customText'])) {
                    $array['customText'] = $this->translateText($sourceSite->language, $targetSite->language, $array['customText']);
                }
                $array['type'] = $value->type; // add type, because they forgot to return it with toArray?
                return $array;
            } catch (\Throwable $throwable) {
                // too bad, f*** linkfields
                return null;
            }
        }
        return null;
    }

    public function translateLinkitField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): ?array
    {
        $value = $element->getFieldValue($field->handle);
        if ($field->allowCustomText && $value->customText != null) {
            $value->customText = $this->translateText($sourceSite->language, $targetSite->language, $value->customText);
        }
        return $field->serializeValue($value, $element);
    }

    public function translateHyperField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): ?array
    {
        $values = $element->getSerializedFieldValues()[$field->handle];
        foreach ($values as $i => $value) {
            try {
                if (!empty($value['linkText'])) {
                    $values[$i]['linkText'] = $this->translateText($sourceSite->language, $targetSite->language, $value['linkText']);
                }
                if (!empty($value['linkSiteId']) && $value['linkSiteId'] == $sourceSite->id) {
                    $values[$i]['linkSiteId'] = $targetSite->id;
                }
            } catch (\Throwable $throwable) {
                // might fail, but we still want to continue
                MultiTranslator::error([
                    'message' => 'Error translating Hyper field',
                    'error' => $throwable->getMessage(),
                ]);
            }
        }
        return $values;
    }

    public function translateEtherSeoField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): ?array
    {
        $value = $element->getFieldValue($field->handle);

        $titles = []; $description = null;

        foreach ($value->titleRaw as $titleValue) {
            $titles[] = $this->translateText($sourceSite->language, $targetSite->language, $titleValue);
        }

        if(!empty($value->descriptionRaw)) {
            $description = $this->translateText($sourceSite->language, $targetSite->language, $value->descriptionRaw);
        }

        return [
            'titleRaw' => $titles,
            'descriptionRaw' => $description
        ];
    }

    public function translateSeomaticField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): ?array
    {
        $serialized = $element->getSerializedFieldValues([$field->handle])[$field->handle];

        $textFields = [
            'seoTitle', 'seoDescription', 'seoKeywords', 'seoImageDescription',
            'twitterTitle', 'twitterDescription', 'twitterImageDescription',
            'ogTitle', 'ogDescription', 'ogImageDescription',
            ];

        if (isset($serialized['metaBundleSettings'])) {
            foreach ($textFields as $textField) {
                $currentValue = $serialized['metaGlobalVars'][$textField] ?? null;
                if ($currentValue && $serialized['metaBundleSettings'][$textField."Source"] == 'fromCustom') {
                    $serialized['metaGlobalVars'][$textField] = $this->translateText($sourceSite->language, $targetSite->language, $currentValue);
                }
            }
        }

        return $serialized;
    }

    public function translateVizyField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): ?array
    {
        $nodes = [];
        foreach ($element->getFieldValue($field->handle)->all() as $vizyNode) {
            if (get_class($vizyNode) == 'verbb\vizy\nodes\VizyBlock') {
                $blockElement = $vizyNode->getBlockElement();
                $translatedMatrixValues = $this->translateElementFields($blockElement, $sourceSite, $targetSite);
                $node = $vizyNode->serializeValue($blockElement);
                $node['attrs']['values']['content']['fields'] = $translatedMatrixValues;
                $nodes[] = $node;
            } else {
                // process html content in array
                $node = $this->translateVizyNode($vizyNode->serializeValue($element), $sourceSite, $targetSite);
                $nodes[] = $node;
            }
        }
        return $nodes;
    }

    public function translateVizyNode(array $node, Site $sourceSite, Site $targetSite)
    {
        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $i => $subNode) {
                // go deeper
                $node['content'][$i] = $this->translateVizyNode($subNode, $sourceSite, $targetSite);
            }
        }

        if (!empty($node['text'])) {
            $node['text'] = $this->translateText($sourceSite->language, $targetSite->language, $node['text']);
        }

        return $node;
    }

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

    public function translateText(string $sourceLocale = null, string $targetLocale = null, string $text = null): ?string
    {
        if ($this->getProviderSettings()->getDetectSourceLanguage()) {
            $sourceLocale = null;
        }

        return $this->getApiService()->translate($sourceLocale, $targetLocale, $text);
    }

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

    public function translateLinks(string $translatedValue = null, Site $sourceSite, Site $targetSite): ?string
    {
        if (!$this->getProviderSettings()->getUpdateInternalLinks()) {
            return $translatedValue;
        }

        $matches = [];
        // match pattern like "<a href="{entry:9999@1:url||https://example.com/slug}">link</a>"
        preg_match_all('/{(entry|asset|variant|product):(\d+)@(\d+):/i', $translatedValue, $matches);

        // should have four arrays: full matches, capture groups 1-3
        if (count($matches) == 4 && count($matches[0])) {
            foreach ($matches[0] as $i => $fullMatch) {
                $type = $matches[1][$i];
                $entryId = $matches[2][$i];
                $siteId = $matches[3][$i];
                $class = null;

                if ($type == 'entry') {
                    $class = Entry::class;
                } elseif ($type == 'asset') {
                    $class = Asset::class;
                } elseif ($type == 'variant') {
                    $class = 'craft\commerce\elements\Variant';
                } elseif ($type == 'product') {
                    $class = 'craft\commerce\elements\Product';
                }

                if ($sourceSite->id == $siteId && $class) {
                    $findTarget = $class::find()->siteId($targetSite->id)->status(null)->id($entryId)->one();
                    if ($findTarget) {
                        $targetSiteId = $targetSite->id;
                        $translatedMatch = '{'.$type.':'.$entryId.'@'.$targetSiteId.':';
                        $translatedValue = str_replace($fullMatch, $translatedMatch, $translatedValue);
                    }
                }
            }
        }

        return $translatedValue;
    }

    public function translateNestedEntries(string $translatedValue = null, Site $sourceSite, Site $targetSite): ?string
    {
        if (!$this->getProviderSettings()->getProcessNestedEntries()) {
            return $translatedValue;
        }

        $matches = [];
        preg_match_all('/<craft-entry data-entry-id="(\d+)" ?(data-site-id="\d+")?>/i', $translatedValue, $matches);

        // should have two arrays: full matches, capture group 1-2
        if (count($matches) == 3 && count($matches[0])) {
            foreach ($matches[0] as $i => $fullMatch) {
                $entryId = $matches[1][$i];

                $sourceEntry = Entry::find()->siteId($sourceSite->id)->id($entryId)->one();
                $targetEntry = $sourceEntry ? $this->translateElement($sourceEntry, $sourceSite, $targetSite, false) : null;
                if ($targetEntry) {
                    $translatedValue = str_replace($fullMatch, "<craft-entry data-entry-id=\"$targetEntry->id\" data-site-id=\"$targetEntry->siteId\">", $translatedValue);
                }
            }
        }

        return $translatedValue;
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
}
