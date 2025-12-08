<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;

class Hyper extends FieldSerializer
{
    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        $values = $element->getSerializedFieldValues()[$this->field->handle];
        return collect($values)->map(function ($value) {
            return [
                'text' => $value['linkText'] ?? null
            ];
        })->all();
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        if (is_array($value)) {
            $serialized = $source->getSerializedFieldValues()[$this->field->handle];

            foreach($serialized as $i => $serializedValue) {
                $serialized[$i]['linkText'] = $value[$i]['text'] ?? null;
                if (!empty($serialized[$i]['linkSiteId']) && $serialized[$i]['linkSiteId'] == $source->siteId) {
                    $serialized[$i]['linkSiteId'] = $target->siteId;
                }
            }

            return parent::setFieldData($source, $target, $serialized ?? null);
        }

        return null;
    }
}