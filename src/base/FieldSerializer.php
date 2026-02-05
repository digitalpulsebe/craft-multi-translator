<?php

namespace digitalpulsebe\craftmultitranslator\base;

use craft\base\Element;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

abstract class FieldSerializer
{
    protected FieldInterface $field;

    public function __construct(FieldInterface $field) {
        $this->field = $field;
    }

    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        return $this->field->serializeValue($element->getFieldValue($this->field->handle), $element);
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        $value = MultiTranslator::getInstance()->translate
            ->onAfterFieldTranslation($source, $this->field, $source->site, $target->site, $value);

        return $value;
    }

    public function translateLinks(string $translatedValue, Site $sourceSite, Site $targetSite): string
    {
        if (!MultiTranslator::getInstance()->settingsService->getProviderSettings()->getUpdateInternalLinks()) {
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

        // The regex looks for a pipe (|) that is NOT preceded by a pipe ((?<!\|))
        // and NOT followed by a pipe ((?!\|)).
        // This is because DeepL API removes the second pipe after translation.
        $translatedValue = preg_replace('/(?<!\|)\|(?!\|)/', '||', $translatedValue);

        return $translatedValue;
    }


}