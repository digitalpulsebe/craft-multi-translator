<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use \Craft;
use craft\commerce\elements\Product;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use yii\web\Response;
use craft\web\Controller;
use digitalpulsebe\craftmultitranslator\helpers\EntryHelper;
use digitalpulsebe\craftmultitranslator\helpers\ProductHelper;

class SidebarController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionTranslate(): Response
    {
        $this->requirePermission('multiTranslateContent');

        $elementId = $this->request->get('elementId');
        $elementType = $this->request->get('elementType');
        $sourceSiteId = $this->request->get('sourceSiteId');
        $targetSiteId = $this->request->get('targetSiteId');

        if ($elementType == Product::class) {
            $element = ProductHelper::one($elementId, $sourceSiteId);
        } else {
            $element = EntryHelper::one($elementId, $sourceSiteId);
        }
        
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
            if ($elementType == Product::class) {
                $target = ProductHelper::one($elementId, $targetSiteId);
            } else {
                $target = EntryHelper::one($elementId, $targetSiteId);
            }
            Craft::$app->session->setError($throwable->getMessage());
            return $this->redirect($target->cpEditUrl);
        }

    }

}
