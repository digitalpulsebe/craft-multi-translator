<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use yii\web\Response;

class TranslateController extends BaseController
{
    public function actionReview(): Response
    {
        $elementId = $this->request->get('elementId');
        $elementType = $this->request->get('elementType');
        $sourceSiteId = $this->request->get('sourceSiteId');

        $element = ElementHelper::one($elementType, $elementId, $sourceSiteId);

        return $this->renderTemplate('multi-translator/_translate/review.twig', [
            'element' => $element,
            'elementType' => $elementType,
            'sourceSiteId' => $sourceSiteId,
        ]);
    }

    public function actionConfirm(): Response
    {
        $elementId = $this->request->post('elementId');
        $elementType = $this->request->post('elementType');
        $sourceSiteId = $this->request->post('sourceSiteId');
        $targetSiteId = $this->request->post('targetSiteId');

        $config = $this->request->post('config');
        MultiTranslator::getInstance()->settingsService->getProviderSettings()->overrideWithConfig($config);

        return $this->translateElement($elementId, $elementType, $sourceSiteId, $targetSiteId);
    }

}
