<?php

namespace justinholt\freenav\controllers;

use Craft;
use craft\db\Query;
use craft\helpers\Json;
use craft\web\Controller;
use justinholt\freenav\elements\Node;
use justinholt\freenav\FreeNav;
use justinholt\freenav\models\Menu;
use justinholt\freenav\models\MenuSiteSettings;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class ImportExportController extends Controller
{
    public function actionExport(int $menuId): Response
    {
        $menu = FreeNav::getInstance()->getMenus()->getMenuById($menuId);

        if (!$menu) {
            throw new NotFoundHttpException('Menu not found');
        }

        $nodes = Node::find()
            ->menuId($menuId)
            ->status(null)
            ->orderBy(['lft' => SORT_ASC])
            ->all();

        $exportData = [
            'freeNav' => '1.0.0',
            'menu' => [
                'name' => $menu->name,
                'handle' => $menu->handle,
                'propagationMethod' => $menu->propagationMethod,
                'maxNodes' => $menu->maxNodes,
                'maxLevels' => $menu->maxLevels,
            ],
            'nodes' => $this->_buildExportNodes($nodes),
        ];

        $json = Json::encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filename = $menu->handle . '-export.json';

        $response = Craft::$app->getResponse();
        $response->sendContentAsFile($json, $filename, [
            'mimeType' => 'application/json',
        ]);

        return $response;
    }

    public function actionImport(): ?Response
    {
        $this->requirePostRequest();

        $file = UploadedFile::getInstanceByName('importFile');

        if (!$file) {
            Craft::$app->getSession()->setError(Craft::t('free-nav', 'No file uploaded.'));
            return null;
        }

        $json = file_get_contents($file->tempName);
        $data = Json::decodeIfJson($json);

        if (!$data || !isset($data['freeNav'], $data['menu'], $data['nodes'])) {
            Craft::$app->getSession()->setError(Craft::t('free-nav', 'Invalid import file format.'));
            return null;
        }

        $menuData = $data['menu'];

        // Check if menu with this handle already exists
        $existingMenu = FreeNav::getInstance()->getMenus()->getMenuByHandle($menuData['handle']);
        if ($existingMenu) {
            $menuData['handle'] .= '_imported';
            $menuData['name'] .= ' (Imported)';
        }

        $menu = new Menu();
        $menu->name = $menuData['name'];
        $menu->handle = $menuData['handle'];
        $menu->propagationMethod = $menuData['propagationMethod'] ?? 'all';
        $menu->maxNodes = $menuData['maxNodes'] ?? null;
        $menu->maxLevels = $menuData['maxLevels'] ?? null;

        // Enable for all sites
        $siteSettings = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings[$site->id] = new MenuSiteSettings([
                'siteId' => $site->id,
                'enabled' => true,
            ]);
        }
        $menu->setSiteSettings($siteSettings);

        if (!FreeNav::getInstance()->getMenus()->saveMenu($menu)) {
            Craft::$app->getSession()->setError(Craft::t('free-nav', 'Couldn\'t create menu from import.'));
            return null;
        }

        // Import nodes
        $this->_importNodes($menu, $data['nodes']);

        Craft::$app->getSession()->setNotice(Craft::t('free-nav', 'Menu imported successfully.'));

        return $this->redirect('free-nav/menus/' . $menu->id . '/build');
    }

    private function _buildExportNodes(array $nodes, int $level = 1): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if ($node->level !== $level) {
                continue;
            }

            $nodeData = [
                'title' => $node->title,
                'nodeType' => $node->nodeType,
                'url' => $node->url,
                'classes' => $node->classes,
                'urlSuffix' => $node->urlSuffix,
                'customAttributes' => $node->getCustomAttributesArray(),
                'newWindow' => $node->newWindow,
                'icon' => $node->icon,
                'badge' => $node->badge,
                'enabled' => $node->enabled,
                'level' => $node->level,
            ];

            // For element-linked nodes, store the UID for portability
            if ($node->linkedElementId) {
                $element = $node->getLinkedElement();
                if ($element) {
                    $nodeData['linkedElementUid'] = $element->uid;
                    $nodeData['linkedElementType'] = $element::class;
                }
            }

            // Get children
            $children = array_filter($nodes, fn($n) => $n->level === $level + 1);
            if (!empty($children)) {
                $nodeData['children'] = $this->_buildExportNodes($nodes, $level + 1);
            }

            $result[] = $nodeData;
        }

        return $result;
    }

    private function _importNodes(Menu $menu, array $nodesData, ?int $parentId = null): void
    {
        foreach ($nodesData as $nodeData) {
            $data = [
                'title' => $nodeData['title'] ?? '',
                'nodeType' => $nodeData['nodeType'] ?? 'custom',
                'url' => $nodeData['url'] ?? null,
                'classes' => $nodeData['classes'] ?? null,
                'urlSuffix' => $nodeData['urlSuffix'] ?? null,
                'customAttributes' => $nodeData['customAttributes'] ?? null,
                'newWindow' => $nodeData['newWindow'] ?? false,
                'icon' => $nodeData['icon'] ?? null,
                'badge' => $nodeData['badge'] ?? null,
                'enabled' => $nodeData['enabled'] ?? true,
                'parentId' => $parentId,
            ];

            // Try to resolve element by UID
            if (!empty($nodeData['linkedElementUid']) && !empty($nodeData['linkedElementType'])) {
                $elementClass = $nodeData['linkedElementType'];
                if (class_exists($elementClass)) {
                    $element = $elementClass::find()
                        ->uid($nodeData['linkedElementUid'])
                        ->status(null)
                        ->one();
                    if ($element) {
                        $data['linkedElementId'] = $element->id;
                    }
                }
            }

            $nodes = FreeNav::getInstance()->getNodes()->addNodes($menu, [$data]);

            // Recursively import children
            if (!empty($nodeData['children']) && !empty($nodes)) {
                $this->_importNodes($menu, $nodeData['children'], $nodes[0]->id);
            }
        }
    }

    public function actionMigrateFromNavigation(): ?Response
    {
        $this->requirePostRequest();

        $db = Craft::$app->getDb();

        if (!$db->tableExists('{{%navigation_navs}}') || !$db->tableExists('{{%navigation_nodes}}')) {
            Craft::$app->getSession()->setError(Craft::t('free-nav', 'Verbb Navigation tables not found.'));
            return $this->redirect('free-nav/settings');
        }

        $navs = (new Query())
            ->select(['*'])
            ->from(['{{%navigation_navs}}'])
            ->where(['dateDeleted' => null])
            ->all();

        if (empty($navs)) {
            Craft::$app->getSession()->setNotice(Craft::t('free-nav', 'No navigations found to migrate.'));
            return $this->redirect('free-nav/settings');
        }

        $migrated = 0;
        $skipped = 0;

        foreach ($navs as $nav) {
            $existing = FreeNav::getInstance()->getMenus()->getMenuByHandle($nav['handle']);
            if ($existing) {
                $skipped++;
                continue;
            }

            $menu = $this->_migrateNav($nav);

            if ($menu) {
                $this->_migrateNavNodes($nav, $menu);
                $migrated++;
            }
        }

        $message = Craft::t('free-nav', '{count} navigation(s) migrated.', ['count' => $migrated]);
        if ($skipped > 0) {
            $message .= ' ' . Craft::t('free-nav', '{count} skipped (handle already exists).', ['count' => $skipped]);
        }

        Craft::$app->getSession()->setNotice($message);

        return $this->redirect('free-nav/menus');
    }

    private function _migrateNav(array $nav): ?Menu
    {
        $menu = new Menu();
        $menu->name = $nav['name'];
        $menu->handle = $nav['handle'];
        $menu->instructions = $nav['instructions'] ?? null;
        $menu->propagationMethod = $nav['propagationMethod'] ?? 'all';
        $menu->maxNodes = $nav['maxNodes'] ?? null;
        $menu->defaultPlacement = $nav['defaultPlacement'] ?? 'end';

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

    private function _migrateNavNodes(array $nav, Menu $menu): void
    {
        $nodes = (new Query())
            ->select([
                'n.id',
                'n.elementId',
                'n.url',
                'n.type',
                'n.classes',
                'n.urlSuffix',
                'n.customAttributes',
                'n.data',
                'n.newWindow',
                'es.title',
                'se.level',
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

        $stack = [];

        foreach ($nodes as $navNode) {
            $level = (int)($navNode['level'] ?? 1);

            while (!empty($stack) && $stack[count($stack) - 1][1] >= $level) {
                array_pop($stack);
            }

            $parentId = !empty($stack) ? $stack[count($stack) - 1][0] : null;

            $nodeData = [
                'title' => $navNode['title'] ?? '',
                'nodeType' => $this->_mapVerbbNodeType($navNode['type']),
                'url' => $navNode['url'] ?? null,
                'linkedElementId' => $this->_resolveVerbbElementId($navNode),
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
                $stack[] = [$created[0]->id, $level];
            }
        }
    }

    private function _mapVerbbNodeType(?string $type): string
    {
        if ($type === null) {
            return 'custom';
        }

        // Normalize backslashes
        $type = str_replace('\\\\', '\\', $type);

        return match ($type) {
            'verbb\navigation\nodetypes\CustomType' => 'custom',
            'verbb\navigation\nodetypes\PassiveType' => 'passive',
            'verbb\navigation\nodetypes\SiteType' => 'site',
            'craft\elements\Entry' => 'entry',
            'craft\elements\Category' => 'category',
            'craft\elements\Asset' => 'asset',
            'craft\commerce\elements\Product' => 'product',
            default => 'custom',
        };
    }

    private function _resolveVerbbElementId(array $navNode): ?int
    {
        $elementId = $navNode['elementId'] ?? null;

        if (!$elementId) {
            return null;
        }

        $exists = (new Query())
            ->from(['{{%elements}}'])
            ->where(['id' => $elementId, 'dateDeleted' => null])
            ->exists();

        return $exists ? (int)$elementId : null;
    }
}
