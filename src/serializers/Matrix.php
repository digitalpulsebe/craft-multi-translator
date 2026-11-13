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
                })
                ->keyBy('id')
                ->map(function ($serializedMatrixElement) {
                    // we don't longer need the 'id' after keying
                    unset($serializedMatrixElement['id']);
                    return $serializedMatrixElement;
                })
                ->all()
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

        $targetQuery = $target->getFieldValue($this->field->handle);
        if ($includeDisabled) {
            $targetQuery->status(null);
        }

        $targetMatrixElements = collect($targetQuery->all())->keyBy('id');
        $targetSerialized = $this->field->serializeValue($targetQuery, $target);

        // loop over original matrix elements
        foreach ($query->all() as $matrixElement) {
            $translatedMatrixValues = $value['children'][$matrixElement->id] ?? [];
            $targetMatrixElement = $targetMatrixElements->get($matrixElement->id);

            // Keep the target site's existing values as the base for blocks that already exist there.
            // Matrix serialization otherwise includes every source field, even fields that weren't
            // translated (e.g. disabled fields or fields without a registered serializer).
            if (isset($targetSerialized[$matrixElement->id])) {
                if (isset($targetSerialized[$matrixElement->id]['title'])) {
                    $serialized[$matrixElement->id]['title'] = $targetSerialized[$matrixElement->id]['title'];
                }

                $serialized[$matrixElement->id]['fields'] = $targetSerialized[$matrixElement->id]['fields'] ?? [];
            }

            if (isset($translatedMatrixValues['title'])) {
                $serialized[$matrixElement->id]['title'] = $translatedMatrixValues['title'];
            }

            if (isset($translatedMatrixValues['fields'])) {
                $translatedFields = MultiTranslator::getInstance()->translate->setElementFieldsTranslations(
                    $matrixElement,
                    $targetMatrixElement ?? $matrixElement,
                    $translatedMatrixValues['fields'],
                );
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
