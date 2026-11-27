<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use digitalpulsebe\craftmultitranslator\services\TargetSitesService;
use yii\web\Response;

class SidebarController extends BaseController
{
    public $enableCsrfValidation = false;

    /**
     * Accepts targetSiteId as a site id, 'all' or 'group:<handle>'.
     */
    public function actionTranslate(): Response
    {
        $elementId = $this->request->get('elementId');
        $elementType = $this->request->get('elementType');
        $sourceSiteId = $this->request->get('sourceSiteId');
        $targetSiteId = $this->request->get('targetSiteId');

        return $this->translateElementToTarget($elementId, $elementType, $sourceSiteId, $targetSiteId);
    }

    /**
     * Kept for backwards compatibility, equals actionTranslate() with targetSiteId 'all'.
     */
    public function actionTranslateToAll(): Response
    {
        $elementId = $this->request->get('elementId');
        $elementType = $this->request->get('elementType');
        $sourceSiteId = $this->request->get('sourceSiteId');

        return $this->translateElementToTarget($elementId, $elementType, $sourceSiteId, TargetSitesService::ALL);
    }

}
