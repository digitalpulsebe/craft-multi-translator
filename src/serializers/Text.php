<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;

class Text extends FieldSerializer
{

    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        return [
            'text' => parent::serialize($element, $sourceSite, $targetSite),
        ];
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        return parent::setFieldData($source, $target, $value['text'] ?? null);
    }
}