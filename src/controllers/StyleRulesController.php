<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use \Craft;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\records\StyleRule;
use yii\web\Response;

class StyleRulesController extends BaseController
{
    public function actionFetch(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('multiTranslateContent');

        try {
            MultiTranslator::getInstance()->translate->getApiProviderByHandle('deepl')->fetchStyleRules();
            $this->setSuccessFlash(Craft::t('multi-translator', 'Style rules fetched from DeepL.'));
        } catch (\Throwable $exception) {
            $this->setFailFlash($exception->getMessage());
        }

        return $this->redirect('multi-translator/style-rules');
    }

    public function actionEnable(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('multiTranslateContent');

        $id = (int) $this->request->getRequiredBodyParam('id');

        $record = $id ? StyleRule::findOne(['id' => $id]) : null;

        if ($record) {
            $record->setAttribute('enabled', 1);
            if ($record->save()) {
                $this->setSuccessFlash(Craft::t('multi-translator', 'Style rule enabled.'));
            }
        }

        return $this->redirect('multi-translator/style-rules');
    }

    public function actionDisable(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('multiTranslateContent');

        $id = (int) $this->request->getRequiredBodyParam('id');

        $record = $id ? StyleRule::findOne(['id' => $id]) : null;

        if ($record) {
            $record->setAttribute('enabled', 0);
            if ($record->save()) {
                $this->setSuccessFlash(Craft::t('multi-translator', 'Style rule disabled.'));
            }
        }

        return $this->redirect('multi-translator/style-rules');
    }
}
