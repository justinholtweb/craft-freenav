<?php

namespace justinholt\freenav\console\controllers;

use Craft;
use craft\console\Controller;
use craft\db\Query;
use justinholt\freenav\FreeNav;
use justinholt\freenav\models\Menu;
use justinholt\freenav\models\MenuSiteSettings;
use yii\base\InvalidArgumentException;
use yii\console\ExitCode;

class MigrateController extends Controller
{
    public $defaultAction = 'from-navigation';

    /**
     * @var string|null The handle of the Structure section to import (from-section).
     */
    public ?string $section = null;

    /**
     * @var string|null The handle of the entries' link field. Without one, each entry links to itself (from-section).
     */
    public ?string $linkField = null;

    /**
     * @var string|null Import the whole section as one menu with this handle, instead of one menu per top-level entry (from-section).
     */
    public ?string $menu = null;

    /**
     * @var string|null The handle of the site to read entries from. Defaults to the primary site (from-section).
     */
    public ?string $site = null;

    /**
     * @var bool Replace the nodes of menus that already exist (from-section).
     */
    public bool $replace = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'from-section') {
            array_push($options, 'section', 'linkField', 'menu', 'site', 'replace');
        }

        return $options;
    }

    /**
     * Imports a Structure section that was used as a menu: each top-level entry becomes a menu
     * and its descendants its nodes (or, with --menu, the whole section one menu). Links come
     * from --link-field: linked entries and assets become element nodes, other links custom
     * URLs, and entries without a link passive nodes.
     */
    public function actionFromSection(): int
    {
        if (!$this->section) {
            $this->stderr("Pass the section to import with --section=<handle>.\n");
            return ExitCode::USAGE;
        }

        $site = null;
        if ($this->site) {
            $site = Craft::$app->getSites()->getSiteByHandle($this->site);
            if (!$site) {
                $this->stderr("There is no site with the handle '{$this->site}'.\n");
                return ExitCode::USAGE;
            }
        }

        $importer = FreeNav::getInstance()->getSectionImporter();

        try {
            $menus = $importer->buildMenus($this->section, $this->linkField ?: null, $this->menu ?: null, $site);
        } catch (InvalidArgumentException $e) {
            $this->stderr($e->getMessage() . "\n");
            return ExitCode::USAGE;
        }

        if ($menus === []) {
            $this->stdout("The section has no entries.\n");
            return ExitCode::OK;
        }

        $failed = 0;

        foreach ($importer->import($menus, $this->replace) as $handle => $result) {
            $this->stdout("{$handle}: ");

            if ($result->getSuccess()) {
                $this->stdout(($result->createdMenu ? 'created, ' : 'replaced, ') . "{$result->nodesImported} node(s)\n");
            } else {
                $failed++;
                $this->stdout("not imported\n");
                foreach ($result->errors as $error) {
                    $this->stderr("  Error: {$error}\n");
                }
            }

            foreach ($result->warnings as $warning) {
                $this->stdout("  Warning: {$warning}\n");
            }
        }

        return $failed ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    public function actionFromNavigation(): int
    {
        if (!$this->_verbbTablesExist()) {
            $this->stderr("Verbb Navigation tables not found. Is the plugin installed?\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $navs = (new Query())
            ->select(['*'])
            ->from(['{{%navigation_navs}}'])
            ->where(['dateDeleted' => null])
            ->all();

        if (empty($navs)) {
            $this->stdout("No navigations found to migrate.\n");
            return ExitCode::OK;
        }

        $this->stdout("Found " . count($navs) . " navigation(s) to migrate.\n\n");

        $migrated = 0;

        foreach ($navs as $nav) {
            $this->stdout("Migrating: {$nav['name']} ({$nav['handle']})...\n");

            // Check if menu with this handle already exists
            $existing = FreeNav::getInstance()->getMenus()->getMenuByHandle($nav['handle']);
            if ($existing) {
                $this->stdout("  Skipped: menu with handle '{$nav['handle']}' already exists.\n\n");
                continue;
            }

            $menu = $this->_migrateNav($nav);

            if ($menu) {
                $nodeCount = $this->_migrateNodes($nav, $menu);
                $this->stdout("  Migrated {$nodeCount} node(s).\n\n");
                $migrated++;
            } else {
                $this->stderr("  Failed to create menu.\n\n");
            }
        }

        $this->stdout("Migration complete. {$migrated} navigation(s) migrated.\n");

        return ExitCode::OK;
    }

    private function _verbbTablesExist(): bool
    {
        $db = Craft::$app->getDb();
        $tablePrefix = $db->tablePrefix ?: '';

        return $db->tableExists($tablePrefix . 'navigation_navs')
            && $db->tableExists($tablePrefix . 'navigation_nodes');
    }

    private function _migrateNav(array $nav): ?Menu
    {
        $menu = new Menu();
        $menu->name = $nav['name'];
        $menu->handle = $nav['handle'];
        $menu->instructions = $nav['instructions'] ?? null;
        $menu->propagationMethod = $nav['propagationMethod'] ?? 'all';
        $menu->maxNodes = $nav['maxNodes'] ?? null;
        $menu->maxLevels = null;
        $menu->defaultPlacement = $nav['defaultPlacement'] ?? 'end';

        // Migrate site settings
        $siteSettings = [];
        $navSites = (new Query())
            ->select(['*'])
            ->from(['{{%navigation_navs_sites}}'])
            ->where(['navId' => $nav['id']])
            ->all();

        if (!empty($navSites)) {
            foreach ($navSites as $navSite) {
                $siteSettings[$navSite['siteId']] = new MenuSiteSettings([
                    'siteId' => (int)$navSite['siteId'],
                    'enabled' => (bool)$navSite['enabled'],
                ]);
            }
        } else {
            // Enable for all sites if no site settings found
            foreach (Craft::$app->getSites()->getAllSites() as $site) {
                $siteSettings[$site->id] = new MenuSiteSettings([
                    'siteId' => $site->id,
                    'enabled' => true,
                ]);
            }
        }

        $menu->setSiteSettings($siteSettings);

        if (!FreeNav::getInstance()->getMenus()->saveMenu($menu)) {
            return null;
        }

        return $menu;
    }

    private function _migrateNodes(array $nav, Menu $menu): int
    {
        // Get nodes from Verbb's structure ordering
        $nodes = (new Query())
            ->select([
                'n.id',
                'n.navId',
                'n.elementId',
                'n.url',
                'n.type',
                'n.classes',
                'n.urlSuffix',
                'n.customAttributes',
                'n.data',
                'n.newWindow',
                'es.title',
                'es.slug',
                'se.level',
                'se.lft',
            ])
            ->from(['n' => '{{%navigation_nodes}}'])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[n.id]]')
            ->innerJoin(['es' => '{{%elements_sites}}'], '[[es.elementId]] = [[n.id]]')
            ->leftJoin(['se' => '{{%structureelements}}'], [
                'and',
                '[[se.elementId]] = [[n.id]]',
                ['se.structureId' => $nav['structureId']],
            ])
            ->where([
                'n.navId' => $nav['id'],
                'e.dateDeleted' => null,
            ])
            ->orderBy(['se.lft' => SORT_ASC])
            ->all();

        if (empty($nodes)) {
            return 0;
        }

        // Build parent stack from levels
        $stack = []; // [[nodeId, freeNavNodeId, level]]
        $count = 0;

        foreach ($nodes as $navNode) {
            $nodeType = $this->_mapNodeType($navNode['type']);
            $level = (int)($navNode['level'] ?? 1);

            // Pop stack until we find the parent level
            while (!empty($stack) && $stack[count($stack) - 1][2] >= $level) {
                array_pop($stack);
            }

            $parentId = !empty($stack) ? $stack[count($stack) - 1][1] : null;

            $nodeData = [
                'title' => $navNode['title'] ?? '',
                'nodeType' => $nodeType,
                'customUrl' => $navNode['url'] ?? null,
                'linkedElementId' => $this->_resolveElementId($navNode),
                'classes' => $navNode['classes'] ?? null,
                'urlSuffix' => $navNode['urlSuffix'] ?? null,
                'customAttributes' => $navNode['customAttributes'] ?? null,
                'data' => $navNode['data'] ?? null,
                'newWindow' => (bool)($navNode['newWindow'] ?? false),
                'enabled' => true,
                'parentId' => $parentId,
            ];

            $created = FreeNav::getInstance()->getNodes()->addNodes($menu, [$nodeData]);

            if (!empty($created)) {
                $stack[] = [$navNode['id'], $created[0]->id, $level];
                $count++;
            }
        }

        return $count;
    }

    private function _mapNodeType(?string $verbbType): string
    {
        if ($verbbType === null) {
            return 'custom';
        }

        return match ($verbbType) {
            'verbb\navigation\nodetypes\CustomType',
            'verbb\\navigation\\nodetypes\\CustomType' => 'custom',

            'verbb\navigation\nodetypes\PassiveType',
            'verbb\\navigation\\nodetypes\\PassiveType' => 'passive',

            'verbb\navigation\nodetypes\SiteType',
            'verbb\\navigation\\nodetypes\\SiteType' => 'site',

            'craft\elements\Entry',
            'craft\\elements\\Entry' => 'entry',

            'craft\elements\Category',
            'craft\\elements\\Category' => 'category',

            'craft\elements\Asset',
            'craft\\elements\\Asset' => 'asset',

            'craft\commerce\elements\Product',
            'craft\\commerce\\elements\\Product' => 'product',

            default => 'custom',
        };
    }

    private function _resolveElementId(array $navNode): ?int
    {
        $elementId = $navNode['elementId'] ?? null;

        if (!$elementId) {
            return null;
        }

        // Verify the element still exists
        $exists = (new Query())
            ->from(['{{%elements}}'])
            ->where(['id' => $elementId, 'dateDeleted' => null])
            ->exists();

        return $exists ? (int)$elementId : null;
    }
}
