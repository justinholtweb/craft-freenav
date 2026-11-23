<?php

namespace justinholt\freenav\migrations;

use craft\db\Migration;

/**
 * Adds `freenav_nodes.craftIcon`: an icon from Craft's icon picker, beside the CSS `icon` class.
 */
class m261123_000000_add_craft_icon extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%freenav_nodes}}', 'craftIcon')) {
            $this->addColumn('{{%freenav_nodes}}', 'craftIcon', $this->string(100)->after('icon'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%freenav_nodes}}', 'craftIcon')) {
            $this->dropColumn('{{%freenav_nodes}}', 'craftIcon');
        }

        return true;
    }
}
