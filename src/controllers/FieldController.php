<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use Craft;
use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use yii\web\Response;

/**
 * Handles per-field translation triggered from the field action menu in the CP element editor.
 */
class FieldController extends BaseController
{
    /**
     * Translate a single field on an element to the requested target site.
     *
     * Builds a disabledFields config override containing every field on the element
     * except the one being translated, then delegates to the standard translateElement()
     * flow — identical to TranslateController::actionConfirm().
     *
     * Accepts POST params:
     *   - elementId    (int)
     *   - elementType  (string, FQCN)
     *   - sourceSiteId (int)
     *   - targetSiteId (int)
     *   - fieldHandle  (string)  the single field to translate
     */
    public function actionTranslate(): Response
    {
        $this->requirePostRequest();

        $elementId    = (int) $this->request->getRequiredBodyParam('elementId');
        $elementType  = $this->request->getRequiredBodyParam('elementType');
        $sourceSiteId = (int) $this->request->getRequiredBodyParam('sourceSiteId');
        $targetSiteId = (int) $this->request->getRequiredBodyParam('targetSiteId');
        $fieldHandle  = $this->request->getRequiredBodyParam('fieldHandle');

        $element = ElementHelper::one($elementType, $elementId, $sourceSiteId);

        if (!$element) {
            return $this->asFailure(Craft::t('multi-translator', 'Element not found.'));
        }

        // Collect every field handle on the element except the one we want to translate,
        // and pass them as the disabledFields override — identical to the review form config.
        $allHandles = array_map(
            fn($field) => $field->handle,
            $element->getFieldLayout()->getCustomFields()
        );

        $allHandles[] = 'title';

        $disabledFields = array_values(array_filter(
            $allHandles,
            fn($handle) => $handle !== $fieldHandle
        ));

        MultiTranslator::getInstance()->settingsService->getProviderSettings()->overrideWithConfig([
            'disabledFields' => $disabledFields,
        ]);

        return $this->translateElement($elementId, $elementType, $sourceSiteId, $targetSiteId);
    }
}
