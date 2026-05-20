<?php

namespace digitalpulsebe\craftmultitranslator\jobs;

use \Craft;
use craft\errors\UnsupportedSiteException;
use craft\queue\BaseJob;
use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use Exception;
use yii\base\InvalidConfigException;

class BulkTranslateJob extends BaseJob
{
    public array $elementIds;
    public string $elementType;
    public string $sourceSiteHandle;
    public string $targetSiteHandle;

    public ?string $description = 'Translating elements...';

    public function execute($queue): void
    {
        $this->setProgress($queue, 1);

        $this->description = "Translating elements...";
        $errors = [];

        $sourceSite = Craft::$app->getSites()->getSiteByHandle($this->sourceSiteHandle);
        $targetSite = Craft::$app->getSites()->getSiteByHandle($this->targetSiteHandle);

        $elements = ElementHelper::all($this->elementType, $this->elementIds, $sourceSite->id);

        $elementCount = count($elements);

        foreach ($elements as $i => $element) {
            $iHuman = $i+1;

            $this->setProgress($queue, $i/$elementCount, "Translating element $iHuman/$elementCount to $targetSite->name");

            try {
                $translatedElement = MultiTranslator::getInstance()->translate->translateElement($element, $sourceSite, $targetSite);

                if (!empty($translatedElement?->errors)) {
                    $errors[$translatedElement->id] = $translatedElement->errors;
                }
            } catch (UnsupportedSiteException | InvalidConfigException $e) {
                // Surfaces when a Category's group is not enabled for the target site,
                // or any other "this element can't live on that site" condition. Per-element
                // recovery — log and continue so one bad target doesn't tank the whole job.
                MultiTranslator::error([
                    'message'   => 'BulkTranslateJob: skipping unsupported site for element',
                    'elementId' => $element->id,
                    'siteHandle' => $this->targetSiteHandle,
                    'exception' => $e->getMessage(),
                ]);
                $errors[$element->id] = ['unsupportedSite' => $e->getMessage()];
            }
        }

        if (count($errors)) {
            $count = count($errors);
            throw new Exception("Validation errors for $count elements. Check the logs.");
        }

        $this->setProgress($queue, 100, 'done');
    }
}
