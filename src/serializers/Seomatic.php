<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;

class Seomatic extends FieldSerializer
{

    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        $serialized = parent::serialize($element, $sourceSite, $targetSite);

        $textFields = [
            'seoTitle', 'seoDescription', 'seoKeywords', 'seoImageDescription',
            'twitterTitle', 'twitterDescription', 'twitterImageDescription',
            'ogTitle', 'ogDescription', 'ogImageDescription',
        ];

        $data = [];
        foreach ($textFields as $textField) {
            $currentValue = $serialized['metaGlobalVars'][$textField] ?? null;
            if ($currentValue && $serialized['metaBundleSettings'][$textField."Source"] == 'fromCustom') {
                $data[$textField] = $currentValue;
            }
        }

        return $data;
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        $serialized = parent::serialize($source, $source->site, $target->site);

        $textFields = [
            'seoTitle', 'seoDescription', 'seoKeywords', 'seoImageDescription',
            'twitterTitle', 'twitterDescription', 'twitterImageDescription',
            'ogTitle', 'ogDescription', 'ogImageDescription',
        ];

        foreach ($textFields as $textField) {
            $currentValue = $serialized['metaGlobalVars'][$textField] ?? null;
            if ($currentValue && $serialized['metaBundleSettings'][$textField."Source"] == 'fromCustom') {
                $serialized['metaGlobalVars'][$textField] = $value[$textField] ?? null;
            }
        }

        return $serialized;
    }
}