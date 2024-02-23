<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use craft\elements\Entry;
use \Craft;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use yii\web\Response;
use craft\web\Controller;
use digitalpulsebe\craftmultitranslator\helpers\EntryHelper;

class SidebarController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionTranslate(): Response
    {
        $this->requirePermission('multiTranslateContent');

        $elementId = $this->request->get('elementId');
        $sourceSiteId = $this->request->get('sourceSiteId');
        $targetSiteId = $this->request->get('targetSiteId');

        $element = EntryHelper::one($elementId, $sourceSiteId);
        $sourceSite = Craft::$app->sites->getSiteById($sourceSiteId);
        $targetSite = Craft::$app->sites->getSiteById($targetSiteId);

        try {
            $translatedElement = MultiTranslator::getInstance()->translate->translateEntry($element, $sourceSite, $targetSite);
            return $this->asSuccess('Entry translated', ['elementId' => $elementId], $translatedElement->cpEditUrl);
        } catch (\Throwable $throwable) {
            $target = EntryHelper::one($elementId, $targetSiteId);
            Craft::$app->session->setError($throwable->getMessage());
            return $this->redirect($target->cpEditUrl);
        }

    }

}
