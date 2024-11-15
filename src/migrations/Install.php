<?php

namespace digitalpulsebe\craftmultitranslator\migrations;

use Craft;
use craft\db\Migration;
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%multitranslator_deepl_glossaries}}')) {
            $this->createTable('{{%multitranslator_deepl_glossaries}}', [
                'id' => $this->primaryKey(),
                'name' => $this->string(),
                'deeplId' => $this->string(),
                'sourceLanguage' => $this->string(5),
                'targetLanguage' => $this->string(5),
                'data' => $this->json(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        if (!$this->db->tableExists('{{%multitranslator_provider_settings}}')) {
            $this->createTable('{{%multitranslator_provider_settings}}', [
                'id' => $this->primaryKey(),
                'settings' => $this->json(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%multitranslator_deepl_glossaries}}');
        $this->dropTableIfExists('{{%multitranslator_provider_settings}}');
        return true;
    }
}
