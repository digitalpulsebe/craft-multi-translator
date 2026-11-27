<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

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

        // nested entries: only translate 'all' and groups to sites the block is already propagated to
        return $this->translateElementToTarget($elementId, $elementType, $sourceSiteId, $targetSiteId, null, false);
    }

}
