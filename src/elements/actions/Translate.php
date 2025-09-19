<?php

namespace digitalpulsebe\craftmultitranslator\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\helpers\UrlHelper;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\jobs\BulkTranslateJob;
use yii\web\UnauthorizedHttpException;

/**
 * Translate element action
 */
class Translate extends ElementAction
{
    public string $sourceSiteHandle;
    public string $targetSiteHandle = '';

    public static function displayName(): string
    {
        return 'Translate';
    }

    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        bulk: true,
        // Return whether the action should be available depending on which elements are selected
        validateSelection: (selectedItems) => {
          return true;
        }
    });
})();
JS, [static::class]);

        return Craft::$app->getView()->renderTemplate('multi-translator/_actions/trigger.twig');
    }

    public function performAction(Craft\elements\db\ElementQueryInterface $query): bool
    {
        $elementIds = $query->ids();

        if (!\Craft::$app->user->checkPermission('multiTranslateContent')) {
            throw new UnauthorizedHttpException('You are not allowed to translate Elements');
        }

        if (!\Craft::$app->user->checkPermission('multiTranslateContentBulk')) {
            throw new UnauthorizedHttpException('You are not allowed to translate Elements in bulk');
        }

        if ($this->targetSiteHandle == '_ALL_') {
            $sourceSiteHandle = $this->sourceSiteHandle;
            $siteHandles = collect(Craft::$app->sites->getAllSites())
                ->filter(function (Site $site) use ($sourceSiteHandle) { return $site->handle != $sourceSiteHandle; })
                ->map(function (Site $site) { return $site->handle; })
                ->all();
        } else {
            $siteHandles = [$this->targetSiteHandle];
        }

        foreach ($siteHandles as $siteHandle) {
            $targetSite = Craft::$app->sites->getSiteByHandle($siteHandle);

            if (!\Craft::$app->user->checkPermission('editSite:'.$targetSite->uid)) {
                throw new UnauthorizedHttpException('You are not allowed to translate Elements for this site: '.$siteHandle);
            }

            Craft::$app
                ->getQueue()
                ->ttr(MultiTranslator::getInstance()->getSettings()->queueJobTtr)
                ->push(new BulkTranslateJob([
                    'elementIds' => $elementIds,
                    'elementType' => $query->elementType,
                    'sourceSiteHandle' => $this->sourceSiteHandle,
                    'targetSiteHandle' => $siteHandle,
                    'description' => 'Translating '.count($elementIds)." elements to $siteHandle..."
                ]))
            ;
        }


        $this->setMessage('Added to queue');

        return true;
    }
}
