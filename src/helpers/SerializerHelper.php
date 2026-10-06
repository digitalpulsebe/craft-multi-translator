<?php

namespace digitalpulsebe\craftmultitranslator\helpers;

use digitalpulsebe\craftmultitranslator\providers\Provider;
use Illuminate\Support\Arr;

class SerializerHelper
{
    public static function serializeToHtmlChuncks(array $data): array
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

            $node = $doc->createElement('node');
            $node->setAttribute('id', $key);

            if (!empty($value)) {
                $cdata = $doc->createCDATASection($value);
                $node->appendChild($cdata);
            }

            $html->appendChild($node);

            if (strlen($doc->saveHTML()) > 50000) {
                // split in new document to avoid large payloads to the api
                $htmls[] = $doc->saveHTML();

                $doc = new \DOMDocument;
                $html = $doc->appendChild($doc->createElement('html'));
            }
        }

        $htmls[] = $doc->saveHTML();
        return $htmls;
    }

    /**
     * Split a flattened [dotPath => value] map into chunks that respect a provider's
     * max item count and max total character length per translateArray() call.
     * @param array<string, string> $flattened
     * @return array<array<string, string>>
     */
    public static function chunkArray(array $flattened, Provider $provider): array
    {
        $maxItems = $provider->getMaxArrayChunkItems();
        $maxChars = $provider->getMaxArrayChunkChars();

        $chunks = [];
        $currentChunk = [];
        $currentChars = 0;

        foreach ($flattened as $key => $value) {
            $exceedsItems = $maxItems !== null && count($currentChunk) >= $maxItems;
            $exceedsChars = ($currentChars + strlen($value)) > $maxChars;

            if ($currentChunk && ($exceedsItems || $exceedsChars)) {
                $chunks[] = $currentChunk;
                $currentChunk = [];
                $currentChars = 0;
            }

            $currentChunk[$key] = $value;
            $currentChars += strlen($value);
        }

        if ($currentChunk) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    public static function unserializeHtmlChuncks(array $htmls): array
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
