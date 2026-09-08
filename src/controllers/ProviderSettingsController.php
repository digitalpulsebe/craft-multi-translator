<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use digitalpulsebe\craftmultitranslator\MultiTranslator;
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

        $posted = $this->request->post('settings', []);

        // Preserve any existing settings not present in this POST
        $existing = ProviderSettings::find()->one();
        $existingSettings = $existing ? ($existing->settings ?? []) : [];

        // Merge root-level general options
        $settings = is_array($existingSettings) ? array_merge($existingSettings, $posted) : $posted;

        // Merge provider sub-arrays under the 'providers' key, per registered handle,
        // so a save of one provider does not wipe settings of other providers.
        if (isset($posted['providers']) && is_array($posted['providers'])) {
            $existingProviders = is_array($existingSettings['providers'] ?? null)
                ? $existingSettings['providers']
                : [];

            foreach (array_keys(MultiTranslator::getInstance()->translate->getApiProviders()) as $handle) {
                if (isset($posted['providers'][$handle]) && is_array($posted['providers'][$handle])) {
                    $existingProviders[$handle] = array_merge(
                        $existingProviders[$handle] ?? [],
                        $posted['providers'][$handle]
                    );
                }
            }

            $settings['providers'] = $existingProviders;
        }

        if (ProviderSettings::createOrUpdate($settings)) {
            $this->setSuccessFlash(\Craft::t('multi-translator', 'Settings saved.'));
        }

        return $this->redirectToPostedUrl();
    }

}
