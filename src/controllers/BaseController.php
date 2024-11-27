<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use \Craft;
use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use yii\web\Response;
use craft\web\Controller;

abstract class BaseController extends Controller
{

    protected function translateElement(int $elementId, string $elementType, int $sourceSiteId, int $targetSiteId): Response
    {
        $this->requirePermission('multiTranslateContent');

        $element = ElementHelper::one($elementType, $elementId, $sourceSiteId);

        $sourceSite = Craft::$app->sites->getSiteById($sourceSiteId);
        $targetSite = Craft::$app->sites->getSiteById($targetSiteId);

        try {
            $translatedElement = MultiTranslator::getInstance()->translate->translateElement($element, $sourceSite, $targetSite);

            if (!empty($translatedElement->errors)) {
                $this->setFailFlash('Validation errors '.json_encode($translatedElement->errors));
                return $this->redirect($translatedElement->cpEditUrl);
            }

            return $this->asSuccess('Element translated', ['elementId' => $elementId], $translatedElement->cpEditUrl);
        } catch (\Throwable $throwable) {
            $redirectElement = ElementHelper::one($elementType, $elementId, $targetSiteId);
            if (empty($redirectElement)) {
                $redirectElement = ElementHelper::one($elementType, $elementId, $sourceSiteId);
            }
            Craft::$app->session->setError($throwable->getMessage());
            return $this->redirect($redirectElement->cpEditUrl);
        }
    }

}
