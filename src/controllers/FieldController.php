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
     * Translate a single field on an element to one or multiple target sites.
     *
     * Accepts POST params:
     *   - elementId    (int)
     *   - elementType  (string, FQCN)
     *   - sourceSiteId (int)
     *   - targetSiteId (int|string) a site id, 'all' or 'group:<handle>'
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

        return $this->translateElementToTarget($elementId, $elementType, $sourceSiteId, $targetSiteId, [$fieldHandle], false);
    }
}
