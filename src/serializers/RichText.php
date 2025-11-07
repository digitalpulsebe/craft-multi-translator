<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

class RichText extends FieldSerializer
{

    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        return [
            'text' => parent::serialize($element, $sourceSite, $targetSite),
        ];
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        $translatedValue = $value['text'] ?? null;

        if ($translatedValue) {
            $translatedValue = $this->translateLinks($translatedValue, $source->site, $target->site);
            $translatedValue = $this->translateNestedEntries($translatedValue, $source->site, $target->site);
        }

        return parent::setFieldData($source, $target, $translatedValue);
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

    public function translateNestedEntries(string $translatedValue, Site $sourceSite, Site $targetSite): string
    {
        if (!MultiTranslator::getInstance()->settingsService->getProviderSettings()->getProcessNestedEntries()) {
            return $translatedValue;
        }

        $matches = [];
        preg_match_all('/<craft-entry data-entry-id="(\d+)" ?(data-site-id="\d+")?>/i', $translatedValue, $matches);

        // should have two arrays: full matches, capture group 1-2
        if (count($matches) == 3 && count($matches[0])) {
            foreach ($matches[0] as $i => $fullMatch) {
                $entryId = $matches[1][$i];

                $sourceEntry = Entry::find()->siteId($sourceSite->id)->id($entryId)->one();
                $targetEntry = $sourceEntry ? MultiTranslator::getInstance()->translate->translateElement($sourceEntry, $sourceSite, $targetSite, false) : null;
                if ($targetEntry) {
                    $translatedValue = str_replace($fullMatch, "<craft-entry data-entry-id=\"$targetEntry->id\" data-site-id=\"$targetEntry->siteId\">", $translatedValue);
                }
            }
        }

        return $translatedValue;
    }
}