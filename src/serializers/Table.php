<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\commerce\errors\NotImplementedException;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;

class Table extends FieldSerializer
{
    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        return [
            'table' => parent::serialize($element, $sourceSite, $targetSite),
        ];
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        $sourceData = parent::serialize($source, $source->site, $target->site);
        $translatedValues = $value['table'] ?? [];

        $targetData = [];

        if (is_array($sourceData)) {
            $textColumns = [];
            foreach ($this->field->columns as $columnName => $columnConfig) {
                if (in_array($columnConfig['type'], ['singleline', 'multiline', 'heading'])) {
                    // only process types with text
                    $textColumns[] = $columnName;
                }
            }

            foreach ($sourceData as $rowIndex => $sourceRow) {
                $targetRow = [];
                foreach ($sourceRow as $columnName => $value) {
                    if (in_array($columnName, $textColumns)) {
                        $targetRow[$columnName] = $translatedValues[$rowIndex][$columnName] ?? '';
                    } else {
                        $targetRow[$columnName] = $value;
                    }
                }
                $targetData[] = $targetRow;
            }
        }

        return $targetData;
    }
}