<?php

namespace digitalpulsebe\craftmultitranslator\migrations;

use Craft;
use craft\db\Migration;

/**
 * m240607_085245_create_deepl_glossaries_table migration.
 */
class m240607_085245_create_deepl_glossaries_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable('{{%multitranslator_deepl_glossaries}}', [
            'id' => $this->primaryKey(),
            'enabled' => $this->boolean()->notNull()->defaultValue(1),
            'name' => $this->string(),
            'deeplId' => $this->string(),
            'sourceLanguage' => $this->string(5),
            'targetLanguage' => $this->string(5),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%multitranslator_deepl_glossaries}}');
        return true;
    }
}
