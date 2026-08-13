<?php

namespace justinholt\freenav\migrations;

use craft\db\Migration;

/**
 * Renames `freenav_nodes.url` to `customUrl` and drops the unused `parentId` column.
 *
 * `url` shadowed Node::getUrl(), so `node.url` in Twig/GraphQL returned the raw custom
 * URL (empty for element-linked nodes) instead of the resolved one. `parentId` was never
 * written — node hierarchy lives in the menu's structure — so it always read as null.
 */
class m260812_000000_rename_url_and_drop_parent_id extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->columnExists('{{%freenav_nodes}}', 'url')) {
            $this->renameColumn('{{%freenav_nodes}}', 'url', 'customUrl');
        }

        if ($this->db->columnExists('{{%freenav_nodes}}', 'parentId')) {
            $this->dropColumn('{{%freenav_nodes}}', 'parentId');
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%freenav_nodes}}', 'customUrl')) {
            $this->renameColumn('{{%freenav_nodes}}', 'customUrl', 'url');
        }

        if (!$this->db->columnExists('{{%freenav_nodes}}', 'parentId')) {
            $this->addColumn('{{%freenav_nodes}}', 'parentId', $this->integer()->after('menuId'));
        }

        return true;
    }
}
