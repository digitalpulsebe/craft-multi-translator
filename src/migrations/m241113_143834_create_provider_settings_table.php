<?php

namespace digitalpulsebe\craftmultitranslator\migrations;

use Craft;
use craft\db\Migration;
use craft\services\ProjectConfig;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\records\ProviderSettings;

/**
 * m241113_143834_create_provider_settings_table migration.
 */
class m241113_143834_create_provider_settings_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%multitranslator_provider_settings}}')) {
            return true;
        }

        $this->createTable('{{%multitranslator_provider_settings}}', [
            'id' => $this->primaryKey(),
            'settings' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $oldSettings = \Craft::$app->getProjectConfig()->get(ProjectConfig::PATH_PLUGINS . '.' . MultiTranslator::getInstance()->handle . '.settings');

        $dbSettings = array_intersect_key($oldSettings,
            array_flip([ // keys to be extracted
                'deeplApiKey',
                'deeplFormality',
                'deeplPreserveFormatting',
                'defaultEnglish',
                'detectSourceLanguage',
                'googleApiKey',
                'openAiKey',
                'openAiModel',
                'openAiTemperature',
                'resetSlug',
                'translationProvider',
                'updateInternalLinks'
            ])
        );

        ProviderSettings::createOrUpdate($dbSettings);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%multitranslator_provider_settings}}');
        return true;
    }
}
