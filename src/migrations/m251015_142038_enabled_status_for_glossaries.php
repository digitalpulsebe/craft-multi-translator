<?php

namespace digitalpulsebe\craftmultitranslator\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251015_142038_enabled_status_for_glossaries migration.
 */
class m251015_142038_enabled_status_for_glossaries extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%multitranslator_deepl_glossaries}}', 'enabled', $this->boolean()->notNull()->defaultValue(true));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropColumn('{{%multitranslator_deepl_glossaries}}', 'enabled');

        return true;
    }
}
