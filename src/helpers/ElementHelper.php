<?php

namespace digitalpulsebe\craftmultitranslator\helpers;

use craft\base\Element;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\elements\Asset;
use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\services\TranslateService;
use Illuminate\Support\Collection;

class ElementHelper
{

    /**
     * @param string $elementType
     * @param int|array $elementIds
     * @param int $siteId
     * @return ElementQuery
     */
    public static function query(string $elementType, int|array $elementIds, int $siteId): ElementQuery
    {
        if ($elementType == 'craft\commerce\elements\Product') {
            return Product::find()->status(null)->id($elementIds)->siteId($siteId);
        } elseif ($elementType == 'craft\commerce\elements\Variant') {
            return Variant::find()->status(null)->id($elementIds)->siteId($siteId);
        } elseif ($elementType == Asset::class) {
            return Asset::find()->status(null)->id($elementIds)->siteId($siteId);
        } else {
            return Entry::find()->drafts(null)->status(null)->id($elementIds)->siteId($siteId);
        }
    }

    /**
     * @param string $elementType
     * @param int $elementId
     * @param int $siteId
     * @return ?Element
     */
    public static function one(string $elementType, int $elementId, int $siteId): ?Element
    {
        return self::query($elementType, $elementId, $siteId)->one();
    }

    /**
     * @param string $elementType
     * @param array $elementIds the element ids to select
     * @param int $siteId the siteId
     * @return Element[]
     */
    public static function all(string $elementType, array $elementIds, int $siteId): array
    {
        return self::query($elementType, $elementIds, $siteId)->all();
    }

    /**
     * @param Element $element
     * @return Collection<FieldInterface>
     */
    public static function getElementTranslatableFields(Element $element): Collection
    {
        $disabledFields = MultiTranslator::getInstance()->settingsService->getProviderSettings()->getDisabledFieldHandles();

        return collect($element->getFieldLayout()->getCustomFields())
            ->filter(function ($field) use ($disabledFields) {
                // filter out disabled fields
                return !in_array($field->handle, $disabledFields);
            })
            ->filter(function ($field) {
                if (in_array(get_class($field), TranslateService::$matrixFields)){
                    // always go deeper in matrix fields, because there might be translatable fields inside the matrix blocks
                    return true;
                }
                // filter only translatable fields
                return $field->translationMethod != Field::TRANSLATION_METHOD_NONE;
            })
            ->keyBy(function ($field) {
                return $field->handle;
            })
        ;
    }
}
