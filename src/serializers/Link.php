<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;

class Link extends FieldSerializer
{
    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        $value = $element->getFieldValue($this->field->handle);
        if ($value) {
            $data = [
                'label' => $value->getLabel(true),
            ];

            if ($value->title) {
                $data['title'] = $value->title;
            }

            return $data;
        }

        return null;
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        if (!empty($this->field) && !empty($value)) {
            $serialized = parent::serialize($source, $source->getSite(), $target->getSite());

            if ($serialized['value']) {
                $serialized['value'] = $this->translateLinks($serialized['value'], $source->site, $target->site);
            }

            $serialized['label'] = $value['label'];

            if (!empty($value['title'])) {
                $serialized['title'] = $value['title'];
            }

            return parent::setFieldData($source, $target, $serialized ?? null);
        }

        return null;
    }
}