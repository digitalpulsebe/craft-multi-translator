<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use digitalpulsebe\craftmultitranslator\services\TargetSitesService;
use yii\web\Response;

class SidebarController extends BaseController
{
    /**
     * Accepts targetSiteId as a site id, 'all' or 'group:<handle>'.
     */
    public function actionTranslate(): Response
    {
        $this->requirePostRequest();

        $elementId = (int) $this->request->getRequiredBodyParam('elementId');
        $elementType = (string) $this->request->getRequiredBodyParam('elementType');
        $sourceSiteId = (int) $this->request->getRequiredBodyParam('sourceSiteId');
        $targetSiteId = $this->request->getRequiredBodyParam('targetSiteId');

        return $this->translateElementToTarget($elementId, $elementType, $sourceSiteId, $targetSiteId);
    }

    /**
     * Kept for backwards compatibility, equals actionTranslate() with targetSiteId 'all'.
     */
    public function actionTranslateToAll(): Response
    {
        $this->requirePostRequest();

        $elementId = (int) $this->request->getRequiredBodyParam('elementId');
        $elementType = (string) $this->request->getRequiredBodyParam('elementType');
        $sourceSiteId = (int) $this->request->getRequiredBodyParam('sourceSiteId');

        return $this->translateElementToTarget($elementId, $elementType, $sourceSiteId, TargetSitesService::ALL);
    }

}
