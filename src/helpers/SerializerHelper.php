<?php

namespace digitalpulsebe\craftmultitranslator\helpers;


use craft\helpers\ArrayHelper;
use Illuminate\Support\Arr;

class SerializerHelper
{

    public static function serialize(array $data): string
    {
        // Create the root <source> element
        $doc = new \DOMDocument;
        $html = $doc->appendChild($doc->createElement('html'));

        // flatten the array
        $dotted = Arr::dot($data);

        foreach ($dotted as $key => $value) {
            if (is_array($value)) {
                $value = null;
            }

            $node = $doc->createElement('node');
            $node->setAttribute('id', $key);

            if (!empty($value)) {
                $cdata = $doc->createCDATASection($value);
                $node->appendChild($cdata);
            }

            $html->appendChild($node);
        }

        return $doc->saveHTML();
    }

    public static function unserialize(string $html): array
    {
        $outputArray = [];
        $pattern = '/<node\s+id=([\'"])([a-zA-Z0-9\._]+)\1>(.*?)<\/node>/s';

        // Use preg_match_all to find all matches
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {

            foreach ($matches as $match) {
                $dotPath = trim($match[2]); // The dot-notated path (e.g., 'fields.f_contentBlock.id')
                $value   = html_entity_decode(trim($match[3])); // The content value

                // Use Arr::set() to insert the value into the array using the dot-notated path
                // Arr::set will automatically create the necessary nested arrays
                Arr::set($outputArray, $dotPath, $value);
            }
        }

        return $outputArray;
    }
}
