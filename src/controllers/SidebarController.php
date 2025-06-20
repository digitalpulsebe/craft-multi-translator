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

    public function actionTranslateToAll()
    {
        $elementId = $this->request->get('elementId');
        $elementType = $this->request->get('elementType');
        $sourceSiteId = $this->request->get('sourceSiteId');

        $this->requirePermission('multiTranslateContent');

        $element = ElementHelper::one($elementType, $elementId, $sourceSiteId);

        $sourceSite = Craft::$app->sites->getSiteById($sourceSiteId);
        $targetSites = collect(\craft\helpers\ElementHelper::supportedSitesForElement($element, true))
            ->filter(function ($site) use ($sourceSiteId) { return $site['siteId'] != $sourceSiteId; })
            ->map(function ($site) { return Craft::$app->sites->getSiteById($site['siteId']); });

        $successSites = collect();

        foreach ($targetSites as $targetSite) {
            try {
                $translatedElement = MultiTranslator::getInstance()->translate->translateElement($element, $sourceSite, $targetSite);

                if (!empty($translatedElement->errors)) {
                    $this->setFailFlash('Validation errors '.json_encode($translatedElement->errors));
                } else {
                    $successSites->push($targetSite);
                }
            } catch (\Throwable $throwable) {
                Craft::$app->session->setError($throwable->getMessage());
            }
        }

        $targetSiteNames = $successSites->pluck('name')->join(', ');
        $this->setSuccessFlash("Element translated to $targetSiteNames");

        return $this->redirect($element->cpEditUrl);
    }

}
