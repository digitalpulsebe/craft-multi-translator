<?php

namespace digitalpulsebe\craftmultitranslator\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;

/**
 * m250417_115217_enable_bulk_permission migration.
 */
class m250417_115217_enable_bulk_permission extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $map = [
            'multitranslatecontent' => 'multitranslatecontentbulk'
        ];

        foreach ($map as $oldPermission => $newPermission) {
            $userIds = (new Query())
                ->select(['upu.userId'])
                ->from(['upu' => Table::USERPERMISSIONS_USERS])
                ->innerJoin(['up' => Table::USERPERMISSIONS], '[[up.id]] = [[upu.permissionId]]')
                ->where(['up.name' => $oldPermission])
                ->column($this->db);

            $userIds = array_unique($userIds);

            if (!empty($userIds)) {
                $insert = [];

                $this->insert(Table::USERPERMISSIONS, [
                    'name' => $newPermission,
                ]);
                $newPermissionId = $this->db->getLastInsertID(Table::USERPERMISSIONS);
                foreach ($userIds as $userId) {
                    $insert[] = [$newPermissionId, $userId];
                }

                $this->batchInsert(Table::USERPERMISSIONS_USERS, ['permissionId', 'userId'], $insert);
            }
        }

        $projectConfig = Craft::$app->getProjectConfig();
        foreach ($projectConfig->get('users.groups') ?? [] as $uid => $group) {
            $groupPermissions = array_flip($group['permissions'] ?? []);
            $save = false;

            foreach ($map as $oldPermission => $newPermission) {
                if (isset($groupPermissions[$oldPermission])) {
                    $groupPermissions[$newPermission] = true;
                    $save = true;
                }
            }

            if ($save) {
                $projectConfig->set("users.groups.$uid.permissions", array_keys($groupPermissions));
                $projectConfig->saveModifiedConfigData();
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250417_115217_enable_bulk_permission cannot be reverted.\n";
        return true;
    }
}
