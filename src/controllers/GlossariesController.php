<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use \Craft;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\records\Glossary;
use yii\web\Response;
use craft\web\Controller;

class GlossariesController extends Controller
{
    public function actionEdit(int $id = null): Response
    {
        $this->requirePermission('multiTranslateContent');

        $record = $id ? Glossary::findOne(['id' => $id]) : new Glossary();

        return $this->renderTemplate('multi-translator/glossaries/_edit.twig', ['glossary' => $record]);
    }

    public function actionNew(): Response
    {
        return $this->actionEdit();
    }

    public function actionFetch()
    {
        $this->requirePermission('multiTranslateContent');

        try {
            MultiTranslator::getInstance()->translate->getApiProviderByHandle('deepl')->fetchGlossaries();
            $this->setSuccessFlash(Craft::t('multi-translator', 'Glossaries fetched from DeepL.'));
        } catch (\Throwable $exception) {
            $this->setFailFlash($exception->getMessage());
        }

        return $this->redirect('multi-translator/glossaries');
    }

    public function actionDelete(int $id = null): Response
    {
        $this->requirePermission('multiTranslateContent');

        $record = $id ? Glossary::findOne(['id' => $id]) : null;

        if ($record && $record->delete()) {
            $this->setSuccessFlash(Craft::t('multi-translator', 'Glossary deleted.'));
        }

        return $this->redirect('multi-translator/glossaries');
    }

    public function actionEnable(int $id = null): Response
    {
        $this->requirePermission('multiTranslateContent');

        $record = $id ? Glossary::findOne(['id' => $id]) : null;

        if ($record) {
            $record->setAttribute('enabled', 1);
            if ($record->save()) {
                $this->setSuccessFlash(Craft::t('multi-translator', 'Glossary enabled.'));
            }
        }

        return $this->redirect('multi-translator/glossaries');
    }

    public function actionDisable(int $id = null): Response
    {
        $this->requirePermission('multiTranslateContent');

        $record = $id ? Glossary::findOne(['id' => $id]) : null;

        if ($record) {
            $record->setAttribute('enabled', 0);
            if ($record->save()) {
                $this->setSuccessFlash(Craft::t('multi-translator', 'Glossary disabled.'));
            }
        }

        return $this->redirect('multi-translator/glossaries');
    }

    public function actionSave(): Response
    {
        $this->requirePermission('multiTranslateContent');

        try {
            $record = Glossary::createOrUpdate($this->request->post());

            if ($record->hasErrors()) {
                $this->setFailFlash(Craft::t('multi-translator', 'Validation errors'));
                return $this->renderTemplate('multi-translator/glossaries/_edit.twig', ['glossary' => $record]);
            } else {
                $this->setSuccessFlash(Craft::t('multi-translator', 'Glossary saved/updated.'));
                return $this->redirect('multi-translator/glossaries');
            }
        } catch (\Throwable $exception) {
            $this->setFailFlash($exception->getMessage());
            return $this->redirectToPostedUrl();
        }
    }

}
