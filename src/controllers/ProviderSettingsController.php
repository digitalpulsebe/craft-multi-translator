<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use digitalpulsebe\craftmultitranslator\records\ProviderSettings;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use craft\web\Controller;

class ProviderSettingsController extends Controller
{

    /**
     * @throws ForbiddenHttpException
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function actionUpdate(): Response
    {
        $this->requirePermission('multiTranslateSettings');

        $settings = $this->request->post('settings');

        if (ProviderSettings::createOrUpdate($settings)) {
            $this->setSuccessFlash('Settings saved.');
        }

        return $this->redirectToPostedUrl();
    }

}
