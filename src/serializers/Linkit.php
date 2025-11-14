<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;

class Linkit extends FieldSerializer
{
    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        $value = $element->getFieldValue($this->field->handle);
        if ($this->field->allowCustomText && $value->customText != null) {
            return ['text' => $value->customText];
        }
        return null;
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        if ($this->field->allowCustomText && isset($value['text'])) {
            $serialized = parent::serialize($source, $source->getSite(), $target->getSite());
            $serialized['customText'] = $value['text'];

            return parent::setFieldData($source, $target, $serialized ?? null);
        }

        return null;
    }
}