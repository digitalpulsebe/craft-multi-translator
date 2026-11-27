<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use digitalpulsebe\craftmultitranslator\MultiTranslator;
use yii\web\Response;

class TranslateController extends BaseController
{
    /**
     * The only settings a translator may override for a single translation (see _translate/review.twig).
     * Everything else (provider, API keys, base urls, target sites, ...) requires the settings permission.
     */
    private const OVERRIDABLE_SETTINGS = [
        'disabledFields',
        'saveAsDraft',
        'resetSlug',
        'detectSourceLanguage',
        'updateInternalLinks',
        'processNestedEntries',
    ];

    public function actionReview(): Response
    {
        $elementId = (int) $this->request->getRequiredQueryParam('elementId');
        $elementType = (string) $this->request->getRequiredQueryParam('elementType');
        $sourceSiteId = (int) $this->request->getRequiredQueryParam('sourceSiteId');

        $element = $this->requireTranslatableElement($elementType, $elementId, $sourceSiteId, false);

        return $this->renderTemplate('multi-translator/_translate/review.twig', [
            'element' => $element,
            'elementType' => $elementType,
            'sourceSiteId' => $sourceSiteId,
        ]);
    }

    public function actionConfirm(): Response
    {
        $this->requirePostRequest();

        $elementId = (int) $this->request->getRequiredBodyParam('elementId');
        $elementType = (string) $this->request->getRequiredBodyParam('elementType');
        $sourceSiteId = (int) $this->request->getRequiredBodyParam('sourceSiteId');
        $targetSiteId = $this->request->getRequiredBodyParam('targetSiteId');

        $config = $this->request->getBodyParam('config');
        $config = is_array($config) ? array_intersect_key($config, array_flip(self::OVERRIDABLE_SETTINGS)) : [];

        if (isset($config['disabledFields']) && !is_array($config['disabledFields'])) {
            // an unchecked checkbox group posts an empty string
            $config['disabledFields'] = [];
        }

        MultiTranslator::getInstance()->settingsService->getProviderSettings()->overrideWithConfig($config);

        return $this->translateElementToTarget($elementId, $elementType, $sourceSiteId, $targetSiteId);
    }

}
