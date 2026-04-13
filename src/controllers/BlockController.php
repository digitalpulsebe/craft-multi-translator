<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use Craft;
use craft\elements\Entry;
use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use yii\web\Response;

class BlockController extends BaseController
{
    public function actionReview(): Response
    {
        $elementId = $this->request->post('blockId');
        $sourceSiteId = $this->request->post('sourceSiteId');

        $element = ElementHelper::one(Entry::class, $elementId, $sourceSiteId);

        return $this->asJson([
            'html' => \Craft::$app->getView()->renderTemplate('multi-translator/_translate/block.twig',
                [
                    'element' => $element,
                    'elementId' => $elementId,
                    'sourceSiteId' => $sourceSiteId
                ]
            ),
            'success' => true,
        ]);
    }

    public function actionTranslate(): Response
    {
        $elementId = $this->request->post('blockId');
        $elementType = Entry::class;
        $sourceSiteId = $this->request->post('sourceSiteId');
        $targetSiteId = $this->request->post('targetSiteId');

        if ($targetSiteId == 'all') {
            $element = Entry::find()->siteId($sourceSiteId)->id($elementId)->one();
            $currentUser = Craft::$app->getUser()->getIdentity();

            // fallback return result
            $result = $this->redirect($element->cpEditUrl);

            $targetSiteIds = collect(\craft\helpers\ElementHelper::supportedSitesForElement($element))
                ->filter(function (array $site) use ($currentUser) {
                    return $currentUser->can('editSite:' . $site['siteUid']);
                })
                ->filter(function (array $site) use ($sourceSiteId) {
                    return $site['siteId'] != $sourceSiteId;
                })
                ->pluck('siteId')
                ->values()
                ->all();

            foreach ($targetSiteIds as $targetSiteId) {
                // only last result will return
                $result = $this->translateElement($elementId, $elementType, $sourceSiteId, $targetSiteId);
            }
            return $result;
        } else {
            return $this->translateElement($elementId, $elementType, $sourceSiteId, $targetSiteId);
        }
    }

}
