<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\enums\PropagationMethod;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

class Matrix extends FieldSerializer
{
    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        $query = $element->getFieldValue($this->field->handle);

        $includeDisabled = MultiTranslator::getInstance()->settingsService->getProviderSettings()->getTranslateDisabledMatrixElements();

        if ($includeDisabled) {
            $query->status(null);
        }

        return [
            'children' => $query->collect()->map(function ($matrixElement) use ($sourceSite, $targetSite) {
                return MultiTranslator::getInstance()->translate->serializeElement($matrixElement, $sourceSite, $targetSite);
            })->keyBy('id')->all()
        ];
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        $query = $source->getFieldValue($this->field->handle);

        $includeDisabled = MultiTranslator::getInstance()->settingsService->getProviderSettings()->getTranslateDisabledMatrixElements();
        if ($includeDisabled) {
            $query->status(null);
        }

        // serialize current value
        $serialized = $this->field->serializeValue($query, $source);

        // loop over original matrix elements
        foreach ($query->all() as $matrixElement) {
            $translatedMatrixValues = $value['children'][$matrixElement->id] ?? [];

            if (isset($translatedMatrixValues['title'])) {
                $serialized[$matrixElement->id]['title'] = $translatedMatrixValues['title'];
            }

            if (isset($translatedMatrixValues['fields'])) {
                $translatedFields = MultiTranslator::getInstance()->translate->setElementFieldsTranslations($matrixElement, $target, $translatedMatrixValues['fields']);
                // merge to keep untranslated fields
                $serialized[$matrixElement->id]['fields'] = array_merge($serialized[$matrixElement->id]['fields'], $translatedFields);
            }
        }

        if (get_class($this->field) == 'benf\neo\Field' && $this->field->propagationMethod != PropagationMethod::None) {
            // special case to avoid neo overwriting blocks in all languages
            $serialized = ['blocks' => $serialized];
        }

        return parent::setFieldData($source, $target, $serialized);
    }
}