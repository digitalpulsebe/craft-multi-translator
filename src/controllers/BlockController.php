<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use craft\base\Element;
use craft\elements\Entry;
use craft\web\View;
use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
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

        return $this->translateElement($elementId, $elementType, $sourceSiteId, $targetSiteId);
    }

}
