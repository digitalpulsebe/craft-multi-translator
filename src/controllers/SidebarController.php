<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use \Craft;
use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use yii\web\Response;
use craft\web\Controller;

class SidebarController extends BaseController
{
    public $enableCsrfValidation = false;

    public function actionTranslate(): Response
    {
        $elementId = $this->request->get('elementId');
        $elementType = $this->request->get('elementType');
        $sourceSiteId = $this->request->get('sourceSiteId');
        $targetSiteId = $this->request->get('targetSiteId');

        return $this->translateElement($elementId, $elementType, $sourceSiteId, $targetSiteId);
    }

}
