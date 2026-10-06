<?php

namespace digitalpulsebe\craftmultitranslator\migrations;

use Craft;
use craft\db\Migration;

/**
 * m260914_100000_create_deepl_style_rules_table migration.
 */
class m260914_100000_create_deepl_style_rules_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable('{{%multitranslator_deepl_style_rules}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(),
            'enabled' => $this->boolean()->notNull()->defaultValue(false),
            'deeplId' => $this->string(),
            'language' => $this->string(10),
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
        $this->dropTableIfExists('{{%multitranslator_deepl_style_rules}}');
        return true;
    }
}
