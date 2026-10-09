<?php

namespace justinholt\freenav\controllers;

use Craft;
use craft\db\Query;
use craft\web\Controller;
use justinholt\freenav\FreeNav;
use justinholt\freenav\models\Menu;
use justinholt\freenav\models\MenuSiteSettings;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class ImportExportController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireCpRequest();
        $this->requirePermission('accessPlugin-free-nav');

        // Importing and migrating create menus, which are project config.
        if ($action->id !== 'export') {
            if (!FreeNav::getInstance()->getMenus()->canManageMenus(Craft::$app->getUser()->getIdentity())) {
                throw new ForbiddenHttpException('User not authorized to manage menus.');
            }

            if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
                throw new ForbiddenHttpException('Menus can only be changed where admin changes are allowed.');
            }
        }

        return true;
    }

    public function actionExport(int $menuId): Response
    {
        $menu = FreeNav::getInstance()->getMenus()->getMenuById($menuId);

        if (!$menu) {
            throw new NotFoundHttpException('Menu not found');
        }

        if (!FreeNav::getInstance()->getMenus()->canManageMenu(Craft::$app->getUser()->getIdentity(), $menu)) {
            throw new ForbiddenHttpException('User not authorized to export this menu.');
        }

        $json = FreeNav::getInstance()->getMenuTransfer()->encode(
            FreeNav::getInstance()->getMenuTransfer()->exportMenu($menu),
        );
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

        $transfer = FreeNav::getInstance()->getMenuTransfer();
        $data = $transfer->decode((string)file_get_contents($file->tempName));

        if (!$data) {
            Craft::$app->getSession()->setError(Craft::t('free-nav', 'Invalid import file format.'));
            return null;
        }

        // A menu with the same handle is left alone; the import becomes a copy beside it.
        $result = $transfer->importMenu($data, renameOnConflict: true);

        if (!$result->getSuccess()) {
            Craft::$app->getSession()->setError(implode(' ', $result->errors));
            return null;
        }

        $notice = Craft::t('free-nav', 'Menu imported successfully.');
        if ($result->warnings) {
            $notice .= ' ' . implode(' ', $result->warnings);
        }
        Craft::$app->getSession()->setNotice($notice);

        return $this->redirect('free-nav/menus/' . $result->menu->id . '/build');
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
                'customUrl' => $navNode['url'] ?? null,
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
