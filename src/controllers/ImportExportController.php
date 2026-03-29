<?php

namespace justinholt\freenav\controllers;

use Craft;
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
                    $nodeData['linkedElementType'] = get_class($element);
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
}
