<?php

namespace digitalpulsebe\craftmultitranslator\serializers;

use craft\base\Element;
use craft\enums\PropagationMethod;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\base\FieldSerializer;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

class Vizy extends FieldSerializer
{

    public function serialize(Element $element, Site $sourceSite, Site $targetSite): mixed
    {
        $nodes = [];
        foreach ($element->getFieldValue($this->field->handle)->all() as $vizyNode) {
            if (get_class($vizyNode) == 'verbb\vizy\nodes\VizyBlock') {
                $blockElement = $vizyNode->getBlockElement();
                $nodes[] = [
                    'element' => MultiTranslator::getInstance()->translate->serializeElement($blockElement, $sourceSite, $targetSite)
                ];
            } else {
                // process html content in array
                $nodes[] = $this->serializeVizyNode($vizyNode->serializeValue($element));
            }
        }

        return $nodes;
    }

    public function serializeVizyNode(array $node)
    {
        $data = [];

        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $i => $subNode) {
                // go deeper
                $data['content'][$i] = $this->serializeVizyNode($subNode);
            }
        }

        if (!empty($node['text'])) {
            $data['text'] = $node['text'];
        }

        return $data;
    }

    public function setFieldData(Element $source, Element $target, mixed $value): mixed
    {
        $nodes = [];
        foreach ($source->getFieldValue($this->field->handle)->all() as $nodeIndex => $vizyNode) {
            $translatedNode = $value[$nodeIndex] ?? null;
            if ($translatedNode) {
                if (get_class($vizyNode) == 'verbb\vizy\nodes\VizyBlock') {
                    $blockElement = $vizyNode->getBlockElement();
                    $targetFields = MultiTranslator::getInstance()->translate->setElementFieldsTranslations($blockElement, $target, $translatedNode['element']['fields']);
                    $blockElement->setFieldValues($targetFields);
                    $serializedNode = $vizyNode->serializeValue($blockElement);
                    $nodes[] = $serializedNode;
                } else {
                    // process html content in array
                    $serializedNode = $vizyNode->serializeValue($source);
                    $nodes[] = $this->setNodeTranslation($serializedNode, $translatedNode);
                }
            }
        }

        return parent::setFieldData($source, $target, $nodes);
    }

    public function setNodeTranslation($node, $translatedValue = null): array
    {
        if (!empty($translatedValue['text'])) {
            $node['text'] = $translatedValue['text'];
        }

        if (isset($node['content']) && is_array($node['content'])) {
            foreach($node['content'] as $i => $subNode) {
                $translatedContent = $translatedValue['content'][$i] ?? null;
                $node['content'][$i] = $this->setNodeTranslation($subNode, $translatedContent);
            }
        }

        return $node;
    }
}