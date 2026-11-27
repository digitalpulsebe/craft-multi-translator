<?php

namespace digitalpulsebe\craftmultitranslator\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\jobs\BulkTranslateJob;
use yii\web\ForbiddenHttpException;
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
        return Craft::t('multi-translator', 'Translate');
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

        return Craft::$app->getView()->renderTemplate('multi-translator/_actions/trigger.twig', [
            'sourceSiteHandle' => $this->sourceSiteHandle,
        ]);
    }

    public function performAction(Craft\elements\db\ElementQueryInterface $query): bool
    {
        $elementIds = $query->ids();

        if (!\Craft::$app->user->checkPermission('multiTranslateContent')) {
            throw new UnauthorizedHttpException(Craft::t('multi-translator', 'You are not allowed to translate Elements'));
        }

        if (!\Craft::$app->user->checkPermission('multiTranslateContentBulk')) {
            throw new UnauthorizedHttpException(Craft::t('multi-translator', 'You are not allowed to translate Elements in bulk'));
        }

        $targetSitesService = MultiTranslator::getInstance()->targetSites;
        $sourceSite = Craft::$app->sites->getSiteByHandle($this->sourceSiteHandle);

        if (!$sourceSite || !\Craft::$app->user->checkPermission('editSite:'.$sourceSite->uid)) {
            throw new UnauthorizedHttpException(Craft::t('multi-translator', 'You are not allowed to translate Elements for this site: {site}', ['site' => $this->sourceSiteHandle]));
        }

        // the queue job runs without a user: check element permissions up front
        $elementsService = Craft::$app->getElements();
        $saveAsDraft = MultiTranslator::getInstance()->settingsService->getProviderSettings()->getSaveAsDraft();
        foreach ((clone $query)->siteId($sourceSite->id)->all() as $element) {
            $allowed = $elementsService->canSave($element)
                || ($saveAsDraft && $element instanceof Entry && $elementsService->canCreateDrafts($element));

            if (!$elementsService->canView($element) || !$allowed) {
                throw new ForbiddenHttpException(Craft::t('multi-translator', 'You are not allowed to translate Elements'));
            }
        }

        if ($targetSitesService->isMultiTarget($this->targetSiteHandle)) {
            $siteHandles = collect($targetSitesService->resolveTargetSites($this->targetSiteHandle, null, $sourceSite->id))
                ->map(fn(Site $site) => $site->handle)
                ->all();
        } else {
            $siteHandles = [$this->targetSiteHandle];
        }

        foreach ($siteHandles as $siteHandle) {
            $targetSite = Craft::$app->sites->getSiteByHandle($siteHandle);

            if (!$targetSite || !\Craft::$app->user->checkPermission('editSite:'.$targetSite->uid)) {
                throw new UnauthorizedHttpException(Craft::t('multi-translator', 'You are not allowed to translate Elements for this site: {site}', ['site' => $siteHandle]));
            }

            if (!$targetSitesService->isTargetEnabled($targetSite)) {
                throw new ForbiddenHttpException(Craft::t('multi-translator', 'Translating to {site} is disabled in the settings.', ['site' => $targetSite->name]));
            }

            Craft::$app
                ->getQueue()
                ->ttr(MultiTranslator::getInstance()->getSettings()->queueJobTtr)
                ->push(new BulkTranslateJob([
                    'elementIds' => $elementIds,
                    'elementType' => $query->elementType,
                    'sourceSiteHandle' => $this->sourceSiteHandle,
                    'targetSiteHandle' => $siteHandle,
                    'description' => Craft::t('multi-translator', 'Translating {count} elements to {site}...', ['count' => count($elementIds), 'site' => $siteHandle]),
                ]))
            ;
        }


        if (empty($siteHandles)) {
            $this->setMessage(Craft::t('multi-translator', 'No target sites to translate to.'));
            return false;
        }

        $this->setMessage(Craft::t('multi-translator', 'Added to queue'));

        return true;
    }
}
