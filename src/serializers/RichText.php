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