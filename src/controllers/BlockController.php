<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use craft\elements\Entry;
use yii\web\Response;

class BlockController extends BaseController
{
    public function actionReview(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $elementId = (int) $this->request->getRequiredBodyParam('blockId');
        $sourceSiteId = (int) $this->request->getRequiredBodyParam('sourceSiteId');

        $element = $this->requireTranslatableElement(Entry::class, $elementId, $sourceSiteId, false);

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
        $this->requirePostRequest();

        $elementId = (int) $this->request->getRequiredBodyParam('blockId');
        $elementType = Entry::class;
        $sourceSiteId = (int) $this->request->getRequiredBodyParam('sourceSiteId');
        $targetSiteId = $this->request->getRequiredBodyParam('targetSiteId');

        // nested entries: only translate 'all' and groups to sites the block is already propagated to
        return $this->translateElementToTarget($elementId, $elementType, $sourceSiteId, $targetSiteId, null, false);
    }

}
