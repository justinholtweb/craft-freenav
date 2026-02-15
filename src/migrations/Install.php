<?php

namespace justinholt\freenav\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->_createMenusTable();
        $this->_createMenuSitesTable();
        $this->_createNodesTable();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%freenav_nodes}}');
        $this->dropTableIfExists('{{%freenav_menu_sites}}');
        $this->dropTableIfExists('{{%freenav_menus}}');

        return true;
    }

    private function _createMenusTable(): void
    {
        $this->createTable('{{%freenav_menus}}', [
            'id' => $this->primaryKey(),
            'structureId' => $this->integer(),
            'fieldLayoutId' => $this->integer(),
            'name' => $this->string(255)->notNull(),
            'handle' => $this->string(255)->notNull(),
            'instructions' => $this->text(),
            'propagationMethod' => $this->string(20)->notNull()->defaultValue('all'),
            'maxNodes' => $this->integer(),
            'maxLevels' => $this->smallInteger(),
            'defaultPlacement' => $this->string(10)->notNull()->defaultValue('end'),
            'permissions' => $this->text(),
            'sortOrder' => $this->smallInteger()->notNull()->defaultValue(0),
            'dateDeleted' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%freenav_menus}}', ['handle'], true);
        $this->createIndex(null, '{{%freenav_menus}}', ['structureId']);
        $this->createIndex(null, '{{%freenav_menus}}', ['fieldLayoutId']);
        $this->createIndex(null, '{{%freenav_menus}}', ['dateDeleted']);

        $this->addForeignKey(null, '{{%freenav_menus}}', ['structureId'], '{{%structures}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%freenav_menus}}', ['fieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'SET NULL');
    }

    private function _createMenuSitesTable(): void
    {
        $this->createTable('{{%freenav_menu_sites}}', [
            'id' => $this->primaryKey(),
            'menuId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%freenav_menu_sites}}', ['menuId', 'siteId'], true);

        $this->addForeignKey(null, '{{%freenav_menu_sites}}', ['menuId'], '{{%freenav_menus}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%freenav_menu_sites}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE');
    }

    private function _createNodesTable(): void
    {
        $this->createTable('{{%freenav_nodes}}', [
            'id' => $this->integer()->notNull(),
            'menuId' => $this->integer()->notNull(),
            'parentId' => $this->integer(),
            'linkedElementId' => $this->integer(),
            'nodeType' => $this->string(50)->notNull(),
            'url' => $this->text(),
            'classes' => $this->string(255),
            'urlSuffix' => $this->string(255),
            'customAttributes' => $this->text(),
            'data' => $this->text(),
            'newWindow' => $this->boolean()->notNull()->defaultValue(false),
            'icon' => $this->string(255),
            'badge' => $this->string(100),
            'visibilityRules' => $this->text(),
            'deletedWithMenu' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addPrimaryKey(null, '{{%freenav_nodes}}', ['id']);
        $this->createIndex(null, '{{%freenav_nodes}}', ['menuId']);
        $this->createIndex(null, '{{%freenav_nodes}}', ['nodeType']);
        $this->createIndex(null, '{{%freenav_nodes}}', ['linkedElementId']);

        $this->addForeignKey(null, '{{%freenav_nodes}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%freenav_nodes}}', ['menuId'], '{{%freenav_menus}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%freenav_nodes}}', ['linkedElementId'], '{{%elements}}', ['id'], 'SET NULL');
    }
}
