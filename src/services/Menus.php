<?php

namespace justinholt\freenav\services;

use Craft;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\Structure;
use justinholt\freenav\elements\Node;
use justinholt\freenav\events\MenuEvent;
use justinholt\freenav\FreeNav;
use justinholt\freenav\models\Menu;
use justinholt\freenav\models\MenuSiteSettings;
use justinholt\freenav\records\MenuRecord;
use justinholt\freenav\records\MenuSiteRecord;
use yii\base\Component;
use yii\base\Exception;

class Menus extends Component
{
    public const EVENT_BEFORE_SAVE_MENU = 'beforeSaveMenu';
    public const EVENT_AFTER_SAVE_MENU = 'afterSaveMenu';
    public const EVENT_BEFORE_DELETE_MENU = 'beforeDeleteMenu';
    public const EVENT_AFTER_DELETE_MENU = 'afterDeleteMenu';

    private ?array $_menus = null;

    public function getAllMenus(): array
    {
        if ($this->_menus !== null) {
            return $this->_menus;
        }

        $rows = (new Query())
            ->select(['*'])
            ->from(['{{%freenav_menus}}'])
            ->where(['dateDeleted' => null])
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        $this->_menus = [];
        foreach ($rows as $row) {
            $this->_menus[] = new Menu($row);
        }

        return $this->_menus;
    }

    public function getMenuById(int $id): ?Menu
    {
        $row = (new Query())
            ->select(['*'])
            ->from(['{{%freenav_menus}}'])
            ->where(['id' => $id, 'dateDeleted' => null])
            ->one();

        return $row ? new Menu($row) : null;
    }

    public function getMenuByHandle(string $handle): ?Menu
    {
        $row = (new Query())
            ->select(['*'])
            ->from(['{{%freenav_menus}}'])
            ->where(['handle' => $handle, 'dateDeleted' => null])
            ->one();

        return $row ? new Menu($row) : null;
    }

    public function getMenuByUid(string $uid): ?Menu
    {
        $row = (new Query())
            ->select(['*'])
            ->from(['{{%freenav_menus}}'])
            ->where(['uid' => $uid, 'dateDeleted' => null])
            ->one();

        return $row ? new Menu($row) : null;
    }

    public function getEditableMenus(): array
    {
        $user = Craft::$app->getUser()->getIdentity();

        if (!$user) {
            return [];
        }

        if ($user->admin) {
            return $this->getAllMenus();
        }

        return array_filter($this->getAllMenus(), function (Menu $menu) use ($user) {
            return $user->can('freeNav-manageMenu:' . $menu->uid)
                || $user->can('freeNav-manageMenus');
        });
    }

    public function getMenuSiteSettings(int $menuId): array
    {
        $rows = (new Query())
            ->select(['*'])
            ->from(['{{%freenav_menu_sites}}'])
            ->where(['menuId' => $menuId])
            ->all();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['siteId']] = new MenuSiteSettings($row);
        }

        return $settings;
    }

    public function saveMenu(Menu $menu, bool $runValidation = true): bool
    {
        $isNew = !$menu->id;

        // Fire before event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_MENU)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_MENU, new MenuEvent([
                'menu' => $menu,
                'isNew' => $isNew,
            ]));
        }

        if ($runValidation && !$menu->validate()) {
            Craft::info('Menu not saved due to validation error.', __METHOD__);
            return false;
        }

        // Ensure UID
        if (!$menu->uid) {
            $menu->uid = StringHelper::UUID();
        }

        // Save to project config
        $configPath = "freeNav.menus.{$menu->uid}";
        $configData = $menu->getConfig();

        Craft::$app->getProjectConfig()->set($configPath, $configData);

        // Update the menu model with its ID if new
        if ($isNew) {
            $record = MenuRecord::findOne(['uid' => $menu->uid]);
            if ($record) {
                $menu->id = $record->id;
            }
        }

        $this->_menus = null;

        return true;
    }

    public function handleChangedMenu(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            // Structure
            $structureUid = $data['structure']['uid'] ?? null;
            $structure = null;

            if ($structureUid) {
                $structure = Craft::$app->getStructures()->getStructureByUid($structureUid, true);
            }

            if (!$structure) {
                $structure = new Structure();
                $structure->maxLevels = $data['maxLevels'] ?? null;
                Craft::$app->getStructures()->saveStructure($structure);
            } else {
                $structure->maxLevels = $data['maxLevels'] ?? null;
                Craft::$app->getStructures()->saveStructure($structure);
            }

            // Field layout
            $fieldLayoutId = null;
            if (!empty($data['fieldLayout'])) {
                $fieldLayout = FieldLayout::createFromConfig($data['fieldLayout']);
                $fieldLayout->type = Node::class;
                Craft::$app->getFields()->saveLayout($fieldLayout);
                $fieldLayoutId = $fieldLayout->id;
            }

            // Menu record
            $record = MenuRecord::findOne(['uid' => $uid]);
            $isNew = !$record;

            if (!$record) {
                $record = new MenuRecord();
            }

            $record->uid = $uid;
            $record->structureId = $structure->id;
            $record->fieldLayoutId = $fieldLayoutId;
            $record->name = $data['name'];
            $record->handle = $data['handle'];
            $record->instructions = $data['instructions'] ?? null;
            $record->propagationMethod = $data['propagationMethod'] ?? 'all';
            $record->maxNodes = $data['maxNodes'] ?? null;
            $record->maxLevels = $data['maxLevels'] ?? null;
            $record->defaultPlacement = $data['defaultPlacement'] ?? 'end';
            $record->save(false);

            // Site settings
            MenuSiteRecord::deleteAll(['menuId' => $record->id]);

            if (!empty($data['sites'])) {
                foreach ($data['sites'] as $siteUid => $siteData) {
                    $site = Craft::$app->getSites()->getSiteByUid($siteUid);
                    if ($site) {
                        $siteRecord = new MenuSiteRecord();
                        $siteRecord->menuId = $record->id;
                        $siteRecord->siteId = $site->id;
                        $siteRecord->enabled = $siteData['enabled'] ?? true;
                        $siteRecord->save(false);
                    }
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->_menus = null;

        // Fire after event
        $menu = $this->getMenuByUid($uid);
        if ($menu && $this->hasEventHandlers(self::EVENT_AFTER_SAVE_MENU)) {
            $this->trigger(self::EVENT_AFTER_SAVE_MENU, new MenuEvent([
                'menu' => $menu,
                'isNew' => $isNew ?? false,
            ]));
        }
    }

    public function deleteMenu(Menu $menu): bool
    {
        // Fire before event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_MENU)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_MENU, new MenuEvent([
                'menu' => $menu,
            ]));
        }

        // Soft delete nodes
        $nodes = Node::find()->menuId($menu->id)->status(null)->all();
        foreach ($nodes as $node) {
            $node->deletedWithMenu = true;
            Craft::$app->getElements()->deleteElement($node);
        }

        // Remove from project config
        Craft::$app->getProjectConfig()->remove("freeNav.menus.{$menu->uid}");

        $this->_menus = null;

        return true;
    }

    public function handleDeletedMenu(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];

        $record = MenuRecord::findOne(['uid' => $uid]);

        if (!$record) {
            return;
        }

        $menu = $this->getMenuById($record->id);

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            // Delete nodes
            $nodes = Node::find()->menuId($record->id)->status(null)->all();
            foreach ($nodes as $node) {
                Craft::$app->getElements()->deleteElement($node, true);
            }

            // Delete site settings
            MenuSiteRecord::deleteAll(['menuId' => $record->id]);

            // Delete structure
            if ($record->structureId) {
                Craft::$app->getStructures()->deleteStructureById($record->structureId);
            }

            // Delete field layout
            if ($record->fieldLayoutId) {
                Craft::$app->getFields()->deleteLayoutById($record->fieldLayoutId);
            }

            // Delete menu record
            $record->delete();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->_menus = null;

        // Fire after event
        if ($menu && $this->hasEventHandlers(self::EVENT_AFTER_DELETE_MENU)) {
            $this->trigger(self::EVENT_AFTER_DELETE_MENU, new MenuEvent([
                'menu' => $menu,
            ]));
        }
    }

    public function reorderMenus(array $ids): bool
    {
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            foreach ($ids as $order => $id) {
                Craft::$app->getDb()->createCommand()
                    ->update('{{%freenav_menus}}', ['sortOrder' => $order], ['id' => $id])
                    ->execute();
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->_menus = null;

        return true;
    }

    public function duplicateMenu(Menu $menu): Menu
    {
        $newMenu = new Menu();
        $newMenu->name = $menu->name . ' Copy';
        $newMenu->handle = $menu->handle . 'Copy';
        $newMenu->instructions = $menu->instructions;
        $newMenu->propagationMethod = $menu->propagationMethod;
        $newMenu->maxNodes = $menu->maxNodes;
        $newMenu->maxLevels = $menu->maxLevels;
        $newMenu->defaultPlacement = $menu->defaultPlacement;
        $newMenu->setSiteSettings($menu->getSiteSettings());

        $this->saveMenu($newMenu);

        // Duplicate nodes
        $nodes = Node::find()->menuId($menu->id)->status(null)->all();
        foreach ($nodes as $node) {
            $newNode = new Node();
            $newNode->menuId = $newMenu->id;
            $newNode->title = $node->title;
            $newNode->nodeType = $node->nodeType;
            $newNode->linkedElementId = $node->linkedElementId;
            $newNode->url = $node->url;
            $newNode->classes = $node->classes;
            $newNode->urlSuffix = $node->urlSuffix;
            $newNode->customAttributes = $node->customAttributes;
            $newNode->data = $node->data;
            $newNode->newWindow = $node->newWindow;
            $newNode->icon = $node->icon;
            $newNode->badge = $node->badge;
            $newNode->visibilityRules = $node->visibilityRules;
            $newNode->enabled = $node->enabled;

            Craft::$app->getElements()->saveElement($newNode);
        }

        return $newMenu;
    }
}
