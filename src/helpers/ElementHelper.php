<?php

namespace digitalpulsebe\craftmultitranslator\helpers;

use craft\base\Element;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\elements\Asset;
use craft\elements\db\ElementQuery;
use craft\elements\Entry;

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
}
