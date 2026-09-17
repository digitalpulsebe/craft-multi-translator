<?php

namespace digitalpulsebe\craftmultitranslator\helpers;


use craft\helpers\ArrayHelper;
use Illuminate\Support\Arr;

class SerializerHelper
{
    public static function serialize(array $data): array
    {
        $htmls = array();

        // Create the root <source> element
        $doc = new \DOMDocument;
        $html = $doc->appendChild($doc->createElement('html'));

        // flatten the array
        $dotted = Arr::dot($data);

        foreach ($dotted as $key => $value) {
            if (is_array($value)) {
                $value = null;
            }

            if (empty($value)) {
                continue;
            }

            $node = $doc->createElement('node');
            $node->setAttribute('id', $key);

            $cdata = $doc->createCDATASection($value);
            $node->appendChild($cdata);

            $html->appendChild($node);

            if (strlen($doc->saveHTML()) > 50000) {
                // split in new document too avoid large payloads to the api
                $htmls[] = $doc->saveHTML();

                $doc = new \DOMDocument;
                $html = $doc->appendChild($doc->createElement('html'));
            }
        }

        $htmls[] = $doc->saveHTML();
        return $htmls;
    }

    /**
     * Flatten nested field data into an ordered [dotPath => value] map of translatable
     * leaf values, dropping empty ones. Unlike serialize(), no markup is applied — the
     * values are sent to the translation provider as-is via its native array support,
     * with the dot-paths kept only in PHP to reassemble the result afterward.
     * @return array<string, string>
     */
    public static function flatten(array $data): array
    {
        $flattened = [];

        foreach (Arr::dot($data) as $key => $value) {
            if (is_array($value)) {
                $value = null;
            }

            if (empty($value)) {
                continue;
            }

            $flattened[$key] = $value;
        }

        return $flattened;
    }

    /**
     * Reassemble a flat [dotPath => translatedValue] map, as produced by flatten() and
     * translated positionally, back into the original nested field structure.
     */
    public static function unflatten(array $flattened): array
    {
        $outputArray = [];

        foreach ($flattened as $dotPath => $value) {
            Arr::set($outputArray, $dotPath, $value);
        }

        return $outputArray;
    }

    public static function unserialize(array $htmls): array
    {
        $outputArray = [];

        foreach ($htmls as $html) {
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
        }

        return $outputArray;
    }
}
