<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use \Craft;
use craft\base\ElementInterface;
use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use craft\web\Controller;

abstract class BaseController extends Controller
{

    protected function translateElement(int $elementId, string $elementType, int $sourceSiteId, int $targetSiteId, ?array $fieldHandles = null): Response
    {
        $this->requirePermission('multiTranslateContent');

        $element = ElementHelper::one($elementType, $elementId, $sourceSiteId);

        $sourceSite = Craft::$app->sites->getSiteById($sourceSiteId);
        $targetSite = Craft::$app->sites->getSiteById($targetSiteId);

        $this->requirePermission('editSite:'.$targetSite->uid);

        if (!MultiTranslator::getInstance()->targetSites->isTargetEnabled($targetSite)) {
            throw new ForbiddenHttpException(Craft::t('multi-translator', 'Translating to {site} is disabled in the settings.', ['site' => $targetSite->name]));
        }

        try {
            $translatedElement = MultiTranslator::getInstance()->translate->translateElement($element, $sourceSite, $targetSite, true, $fieldHandles);

            if (empty($translatedElement)) {
                $this->setFailFlash(Craft::t('multi-translator', 'Translation cancelled'));
                return $this->redirect($element->cpEditUrl);
            }

            if (!empty($translatedElement->errors)) {
                $this->setFailFlash(Craft::t('multi-translator', 'Validation errors') . ' ' . json_encode($translatedElement->errors));
                return $this->redirect($translatedElement->cpEditUrl);
            }

            // get parent of translated element, if exists
            $rootOwner = $translatedElement->getRootOwner();

            return $this->asSuccess(Craft::t('multi-translator', 'Element translated'), ['elementId' => $elementId], $rootOwner ? $rootOwner->getCpEditUrl() : $translatedElement->getCpEditUrl());
        } catch (\Throwable $throwable) {
            $redirectElement = ElementHelper::one($elementType, $elementId, $targetSiteId);
            if (empty($redirectElement)) {
                $redirectElement = ElementHelper::one($elementType, $elementId, $sourceSiteId);
            }
            Craft::$app->session->setError($throwable->getMessage());
            return $this->redirect($redirectElement->cpEditUrl);
        }
    }

    /**
     * Translate an element to a target value: a single site id, 'all' or 'group:<handle>'.
     *
     * @param bool $withUnpropagatedSites for multi targets: also translate to supported sites the element doesn't exist in yet
     */
    protected function translateElementToTarget(int $elementId, string $elementType, int $sourceSiteId, int|string $target, ?array $fieldHandles = null, bool $withUnpropagatedSites = true): Response
    {
        $targetSitesService = MultiTranslator::getInstance()->targetSites;

        if (!$targetSitesService->isMultiTarget($target)) {
            return $this->translateElement($elementId, $elementType, $sourceSiteId, (int) $target, $fieldHandles);
        }

        $this->requirePermission('multiTranslateContent');

        $element = ElementHelper::one($elementType, $elementId, $sourceSiteId);

        if (!$element) {
            throw new NotFoundHttpException(Craft::t('multi-translator', 'Element not found.'));
        }

        $sourceSite = Craft::$app->sites->getSiteById($sourceSiteId);
        $targetSites = $targetSitesService->resolveTargetSites($target, $element, $sourceSiteId, $withUnpropagatedSites);
        $redirectUrl = $this->getRootOwnerCpEditUrl($element);

        if (empty($targetSites)) {
            $this->setFailFlash(Craft::t('multi-translator', 'No target sites to translate to.'));
            return $this->redirect($redirectUrl);
        }

        $successSites = [];
        $errors = [];

        foreach ($targetSites as $targetSite) {
            try {
                $translatedElement = MultiTranslator::getInstance()->translate->translateElement($element, $sourceSite, $targetSite, true, $fieldHandles);

                if (empty($translatedElement)) {
                    continue;
                }

                if (!empty($translatedElement->errors)) {
                    $errors[] = $targetSite->name . ': ' . Craft::t('multi-translator', 'Validation errors') . ' ' . json_encode($translatedElement->errors);
                } else {
                    $successSites[] = $targetSite->name;
                }
            } catch (\Throwable $throwable) {
                $errors[] = $targetSite->name . ': ' . $throwable->getMessage();
            }
        }

        if (!empty($errors)) {
            $this->setFailFlash(implode("\n", $errors));
        }

        if (!empty($successSites)) {
            $this->setSuccessFlash(Craft::t('multi-translator', 'Element translated to {sites}', ['sites' => implode(', ', $successSites)]));
        }

        return $this->redirect($redirectUrl);
    }

    private function getRootOwnerCpEditUrl(ElementInterface $element): ?string
    {
        $rootOwner = method_exists($element, 'getRootOwner') ? $element->getRootOwner() : null;

        return ($rootOwner ?? $element)->getCpEditUrl();
    }

}
