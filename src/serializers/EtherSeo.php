<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;

class EtherSeo extends FieldSerializer
{

    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        $value = $element->getFieldValue($this->field->handle);

        return [
            'titleRaw' => $value->titleRaw,
            'descriptionRaw' => $value->descriptionRaw
        ];
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        return [
            'titleRaw' => $value['titleRaw'] ?? [],
            'descriptionRaw' => $value['descriptionRaw'] ?? null
        ];
    }
}