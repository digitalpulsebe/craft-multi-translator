<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

class ContentBlock extends FieldSerializer
{
    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        $contentBlock = $element->getFieldValue($this->field->handle);
        return [
            'fields' => MultiTranslator::getInstance()->translate->serializeElementFields($contentBlock, $sourceSite, $targetSite)
        ];
    }


    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        $contentBlock = $source->getFieldValue($this->field->handle);
        $serialized = $this->field->serializeValue($contentBlock, $source);

        if (!$serialized) {
            $serialized = ['fields' => []];
        }

        // nested fields read their existing target values from the target's own content block
        $targetContentBlock = $this->getTargetFieldValue($target);
        if (!$targetContentBlock instanceof Element) {
            $targetContentBlock = $target;
        }

        $serialized['fields'] = array_merge(
            $serialized['fields'] ?? [],
            MultiTranslator::getInstance()->translate->setElementFieldsTranslations($contentBlock, $targetContentBlock, $value['fields'] ?? [])
        );

        return parent::setFieldData($source, $target, $serialized);
    }
}