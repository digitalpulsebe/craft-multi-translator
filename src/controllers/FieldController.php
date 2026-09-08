<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use Craft;
use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use yii\web\Response;

/**
 * Handles per-field translation triggered from the field action menu in the CP element editor.
 */
class FieldController extends BaseController
{
    /**
     * Render the field translation modal body as JSON {html, success}.
     * Called by Craft.MultiTranslatorFieldModal on open.
     *
     * Accepts POST params:
     *   - elementId    (int)
     *   - elementType  (string, FQCN)
     *   - sourceSiteId (int)
     *   - fieldHandle  (string)
     */
    public function actionReview(): Response
    {
        $elementId    = $this->request->post('elementId');
        $elementType  = $this->request->post('elementType');
        $sourceSiteId = $this->request->post('sourceSiteId');
        $fieldHandle  = $this->request->post('fieldHandle');

        $element = ElementHelper::one($elementType, $elementId, $sourceSiteId);

        return $this->asJson([
            'html' => Craft::$app->getView()->renderTemplate('multi-translator/_translate/field.twig', [
                'element'     => $element,
                'elementId'   => $elementId,
                'elementType' => $elementType,
                'sourceSiteId' => $sourceSiteId,
                'fieldHandle' => $fieldHandle,
            ]),
            'success' => true,
        ]);
    }

    /**
     * Translate a single field on an element to one or all target sites.
     *
     * Accepts POST params:
     *   - elementId    (int)
     *   - elementType  (string, FQCN)
     *   - sourceSiteId (int)
     *   - targetSiteId (int|'all')  use 'all' to translate to every supported site
     *   - fieldHandle  (string)     the single field to translate
     */
    public function actionTranslate(): Response
    {
        $this->requirePostRequest();

        $elementId    = (int) $this->request->getRequiredBodyParam('elementId');
        $elementType  = $this->request->getRequiredBodyParam('elementType');
        $sourceSiteId = (int) $this->request->getRequiredBodyParam('sourceSiteId');
        $targetSiteId = $this->request->getRequiredBodyParam('targetSiteId');
        $fieldHandle  = $this->request->getRequiredBodyParam('fieldHandle');

        $element = ElementHelper::one($elementType, $elementId, $sourceSiteId);

        if (!$element) {
            return $this->asFailure(Craft::t('multi-translator', 'Element not found.'));
        }

        if ($targetSiteId === 'all') {
            $currentUser = Craft::$app->getUser()->getIdentity();

            $result = $this->redirect($element->cpEditUrl);

            $targetSiteIds = collect(\craft\helpers\ElementHelper::supportedSitesForElement($element))
                ->filter(fn(array $site) => $currentUser->can('editSite:' . $site['siteUid']))
                ->filter(fn(array $site) => $site['siteId'] != $sourceSiteId)
                ->pluck('siteId')
                ->values()
                ->all();

            foreach ($targetSiteIds as $siteId) {
                $result = $this->translateElement($elementId, $elementType, $sourceSiteId, $siteId, [$fieldHandle]);
            }

            return $result;
        }

        return $this->translateElement($elementId, $elementType, $sourceSiteId, (int) $targetSiteId, [$fieldHandle]);
    }
}
