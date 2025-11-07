<?php

namespace digitalpulsebe\craftmultitranslator\base;

use craft\base\Element;
use craft\base\FieldInterface;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

abstract class FieldSerializer
{
    protected FieldInterface $field;

    public function __construct(FieldInterface $field) {
        $this->field = $field;
    }

    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        return $this->field->serializeValue($element->getFieldValue($this->field->handle), $element);
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        $value = MultiTranslator::getInstance()->translate
            ->onAfterFieldTranslation($source, $this->field, $source->site, $target->site, $value);

        return $value;
    }

}