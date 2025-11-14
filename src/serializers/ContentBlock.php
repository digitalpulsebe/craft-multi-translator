<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\enums\PropagationMethod;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

class ContentBlock extends FieldSerializer
{
    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        $contentBlock = $element->getFieldValue($this->field->handle);
        return [
            'id' => $contentBlock->id,
            'fields' => MultiTranslator::getInstance()->translate->serializeElementFields($contentBlock, $sourceSite, $targetSite)
        ];
    }


    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        $contentBlock = $source->getFieldValue($this->field->handle);
        $serialized = $this->field->serializeValue($contentBlock, $source);
        $serialized['fields'] = MultiTranslator::getInstance()->translate->setElementFieldsTranslations($contentBlock, $target, $value['fields'] ?? []);

        return parent::setFieldData($source, $target, $serialized);
    }
}