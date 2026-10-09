<?php

namespace justinholt\freenav\services;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\Json;
use justinholt\freenav\elements\Node;
use justinholt\freenav\enums\NodeType;
use justinholt\freenav\FreeNav;
use justinholt\freenav\models\ImportResult;
use justinholt\freenav\models\Menu;
use justinholt\freenav\models\MenuSiteSettings;
use yii\base\Component;

/**
 * Menu JSON export and import, shared by the control panel and the console.
 *
 * A menu's *definition* is project config, its *nodes* are content. So importing nodes into a
 * menu that already exists (`replace`) works anywhere, while an import that has to create the
 * menu needs `allowAdminChanges`, the same as creating one in the control panel.
 */
class MenuTransfer extends Component
{
    public const FORMAT_VERSION = '1.2.0';

    /** Deeper than any real menu; stops a hand-edited file recursing without end. */
    private const MAX_DEPTH = 32;

    public function exportMenu(Menu $menu): array
    {
        return [
            'freeNav' => self::FORMAT_VERSION,
            'menu' => [
                'name' => $menu->name,
                'handle' => $menu->handle,
                'propagationMethod' => $menu->propagationMethod,
                'maxNodes' => $menu->maxNodes,
                'maxLevels' => $menu->maxLevels,
            ],
            'nodes' => $this->_exportTree(FreeNav::getInstance()->getNodes()->findNodesInMenu($menu->id)->all()),
        ];
    }

    public function encode(array $export): string
    {
        return Json::encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Decodes an export file, or returns null when it isn't one.
     */
    public function decode(string $json): ?array
    {
        $data = Json::decodeIfJson($json);

        if (
            !is_array($data)
            || !isset($data['freeNav'], $data['menu'], $data['nodes'])
            || !is_array($data['menu'])
            || !is_array($data['nodes'])
            || !is_string($data['menu']['handle'] ?? null)
        ) {
            return null;
        }

        return $data;
    }

    /**
     * Imports an export file's nodes.
     *
     * - The target menu is `$handle`, or the file's own handle.
     * - It doesn't exist: the menu is created from the file (needs `allowAdminChanges`).
     * - It exists and `$renameOnConflict`: a new menu is created under a free `…_imported` handle
     *   (what the control panel's import does).
     * - It exists and `$replace`: its nodes are replaced; its settings are left as they are.
     * - It exists otherwise: nothing happens and the result says why.
     *
     * Every node is checked before anything is written — custom URLs through the same rule the
     * node editor uses — and the node writes run in one transaction, so a bad file changes nothing.
     */
    public function importMenu(array $data, ?string $handle = null, bool $replace = false, bool $renameOnConflict = false): ImportResult
    {
        $result = new ImportResult();
        $menus = FreeNav::getInstance()->getMenus();
        $menuData = $data['menu'];
        $handle = $handle ?: (string)$menuData['handle'];
        $name = is_string($menuData['name'] ?? null) && $menuData['name'] !== '' ? $menuData['name'] : $handle;

        $menu = $menus->getMenuByHandle($handle);

        if ($menu && $renameOnConflict) {
            [$handle, $name] = $this->_freeHandle($handle, $name);
            $menu = null;
        } elseif ($menu && !$replace) {
            $result->menu = $menu;
            $result->errors[] = Craft::t('free-nav', 'A menu with the handle “{handle}” already exists. Replace its nodes, or import under another handle.', ['handle' => $handle]);

            return $result;
        }

        if (!$menu && !Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $result->errors[] = Craft::t('free-nav', 'There is no menu with the handle “{handle}”, and menus can only be created where admin changes are allowed. Create the menu there first (project config will bring it here), then import its nodes.', ['handle' => $handle]);

            return $result;
        }

        // Settings the new menu would get, so the limits can be checked before it exists.
        $limits = $menu ?? new Menu([
            'maxNodes' => is_numeric($menuData['maxNodes'] ?? null) ? (int)$menuData['maxNodes'] : null,
            'maxLevels' => is_numeric($menuData['maxLevels'] ?? null) ? (int)$menuData['maxLevels'] : null,
        ]);

        $plan = $this->_plan($data['nodes'], $menu, $result);
        $this->_checkLimits($plan, $limits, $result);

        if (!$result->getSuccess()) {
            return $result;
        }

        if (!$menu) {
            $menu = new Menu();
            $menu->name = $name;
            $menu->handle = $handle;
            $menu->propagationMethod = is_string($menuData['propagationMethod'] ?? null) ? $menuData['propagationMethod'] : 'all';
            $menu->maxNodes = $limits->maxNodes;
            $menu->maxLevels = $limits->maxLevels;

            $siteSettings = [];
            foreach (Craft::$app->getSites()->getAllSites() as $site) {
                $siteSettings[$site->id] = new MenuSiteSettings(['siteId' => $site->id, 'enabled' => true]);
            }
            $menu->setSiteSettings($siteSettings);

            if (!$menus->saveMenu($menu)) {
                foreach ($menu->getFirstErrors() as $error) {
                    $result->errors[] = $error;
                }

                return $result;
            }

            $menu = $menus->getMenuById($menu->id) ?? $menu;
            $result->createdMenu = true;
        }

        $result->menu = $menu;

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            if ($replace && !$result->createdMenu) {
                // Deepest first, so no node outlives its parent mid-way.
                $existing = array_reverse(FreeNav::getInstance()->getNodes()->findNodesInMenu($menu->id)->all());

                foreach ($existing as $node) {
                    if (!Craft::$app->getElements()->deleteElement($node)) {
                        throw new \RuntimeException("Couldn't remove node #$node->id.");
                    }
                    $result->nodesRemoved++;
                }
            }

            $this->_write($menu, $plan, null, $result);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $result->nodesImported = 0;
            $result->nodesRemoved = 0;
            $result->errors[] = $e->getMessage();

            // All or nothing: don't leave behind an empty menu the import made.
            if ($result->createdMenu) {
                FreeNav::getInstance()->getMenus()->deleteMenu($menu);
                $result->menu = null;
                $result->createdMenu = false;
            }
        }

        FreeNav::getInstance()->getMenuCache()->invalidate($menu->handle);

        return $result;
    }

    /**
     * Nodes come back in structure order, so each node's children are the run of deeper
     * nodes straight after it.
     */
    private function _exportTree(array $nodes, int &$i = 0, int $level = 1): array
    {
        $items = [];

        while ($i < count($nodes)) {
            $node = $nodes[$i];
            $nodeLevel = (int)($node->level ?? 1);

            if ($nodeLevel < $level) {
                break;
            }

            $i++;
            $item = $this->_exportNode($node);
            $children = $this->_exportTree($nodes, $i, $nodeLevel + 1);

            if ($children !== []) {
                $item['children'] = $children;
            }

            $items[] = $item;
        }

        return $items;
    }

    private function _exportNode(Node $node): array
    {
        $data = [
            'title' => $node->title,
            'nodeType' => $node->nodeType,
            'customUrl' => $node->customUrl,
            'classes' => $node->classes,
            'urlSuffix' => $node->urlSuffix,
            'customAttributes' => $node->getCustomAttributesArray(),
            'newWindow' => $node->newWindow,
            'icon' => $node->icon,
            'badge' => $node->badge,
            'visibilityRules' => $node->getVisibilityRulesArray(),
            'data' => $node->getDataArray(),
            'enabled' => $node->enabled,
            'level' => $node->level,
        ];

        // Element links travel by UID: IDs differ between environments.
        if ($node->linkedElementId && ($element = $node->getLinkedElement())) {
            $data['linkedElementUid'] = $element->uid;
            $data['linkedElementType'] = $element::class;
        }

        return $data;
    }

    /**
     * Turns the file's nodes into unsaved, validated Node models, collecting every problem.
     *
     * @return array<int, array{node: Node, children: array}>
     */
    private function _plan(array $items, ?Menu $menu, ImportResult $result, int $depth = 1, string $path = ''): array
    {
        $plan = [];

        if ($depth > self::MAX_DEPTH) {
            $result->errors[] = Craft::t('free-nav', 'Nodes are nested more than {max} levels deep.', ['max' => self::MAX_DEPTH]);

            return $plan;
        }

        foreach (array_values($items) as $i => $item) {
            $label = ltrim($path . '/' . ($i + 1), '/');

            if (!is_array($item)) {
                $result->errors[] = Craft::t('free-nav', 'Node {path} is not an object.', ['path' => $label]);
                continue;
            }

            $type = NodeType::tryFrom((string)($item['nodeType'] ?? NodeType::Custom->value));

            if (!$type) {
                $result->errors[] = Craft::t('free-nav', 'Node {path} has an unknown type “{type}”.', ['path' => $label, 'type' => (string)$item['nodeType']]);
                continue;
            }

            $node = new Node();
            $node->menuId = $menu?->id;
            $node->title = is_scalar($item['title'] ?? null) ? trim((string)$item['title']) : '';
            $node->nodeType = $type->value;
            // 'url' is accepted for backwards compatibility with older exports
            $node->customUrl = $this->_string($item['customUrl'] ?? $item['url'] ?? null);
            $node->classes = $this->_string($item['classes'] ?? null);
            $node->urlSuffix = $this->_string($item['urlSuffix'] ?? null);
            $node->customAttributes = $this->_array($item['customAttributes'] ?? null);
            $node->data = $this->_array($item['data'] ?? null);
            $node->visibilityRules = $this->_array($item['visibilityRules'] ?? null);
            $node->newWindow = (bool)($item['newWindow'] ?? false);
            $node->icon = $this->_string($item['icon'] ?? null);
            $node->badge = $this->_string($item['badge'] ?? null);
            $node->enabled = (bool)($item['enabled'] ?? true);

            $element = $this->_linkedElement($type, $item);

            if ($element) {
                $node->linkedElementId = $element->id;

                if ($node->title === '') {
                    $node->title = (string)($element->title ?? '');
                }
            } elseif ($type->isElement()) {
                $result->warnings[] = Craft::t('free-nav', 'Node {path} (“{title}”) links to a {type} that doesn’t exist here; it will have no link until one is chosen.', [
                    'path' => $label,
                    'title' => $node->title,
                    'type' => strtolower($type->label()),
                ]);
            }

            if ($node->title === '') {
                $result->errors[] = Craft::t('free-nav', 'Node {path} has no title.', ['path' => $label]);
            }

            if (!$node->validate(['customUrl'])) {
                $result->errors[] = Craft::t('free-nav', 'Node {path} (“{title}”): {error}', [
                    'path' => $label,
                    'title' => $node->title,
                    'error' => $node->getFirstError('customUrl'),
                ]);
            }

            $children = isset($item['children']) && is_array($item['children'])
                ? $this->_plan($item['children'], $menu, $result, $depth + 1, $label)
                : [];

            $plan[] = ['node' => $node, 'children' => $children];
        }

        return $plan;
    }

    /**
     * Only an element of the class the node type links to — the file names the class, and
     * that must never reach an arbitrary class's `find()`.
     */
    private function _linkedElement(NodeType $type, array $item): ?ElementInterface
    {
        $elementType = $type->elementType();
        $uid = $item['linkedElementUid'] ?? null;

        if (!$elementType || !is_string($uid) || $uid === '' || !class_exists($elementType)) {
            return null;
        }

        if (isset($item['linkedElementType']) && $item['linkedElementType'] !== $elementType) {
            return null;
        }

        /** @var class-string<ElementInterface> $elementType */
        return $elementType::find()->uid($uid)->status(null)->site('*')->unique()->one();
    }

    private function _checkLimits(array $plan, Menu $menu, ImportResult $result): void
    {
        [$count, $depth] = $this->_measure($plan);

        if ($menu->maxNodes && $count > $menu->maxNodes) {
            $result->errors[] = Craft::t('free-nav', 'The file has {count} nodes; the menu allows {max}.', ['count' => $count, 'max' => $menu->maxNodes]);
        }

        if ($menu->maxLevels && $depth > $menu->maxLevels) {
            $result->errors[] = Craft::t('free-nav', 'The file nests nodes {depth} levels deep; the menu allows {max}.', ['depth' => $depth, 'max' => $menu->maxLevels]);
        }
    }

    /**
     * @return array{int, int} node count, deepest level
     */
    private function _measure(array $plan, int $level = 1): array
    {
        $count = 0;
        $depth = $plan ? $level : 0;

        foreach ($plan as $entry) {
            [$c, $d] = $this->_measure($entry['children'], $level + 1);
            $count += 1 + $c;
            $depth = max($depth, $d);
        }

        return [$count, $depth];
    }

    private function _write(Menu $menu, array $plan, ?Node $parent, ImportResult $result): void
    {
        $nodes = FreeNav::getInstance()->getNodes();
        $siteId = $nodes->getDefaultSiteId($menu);

        foreach ($plan as $entry) {
            /** @var Node $node */
            $node = $entry['node'];
            $node->menuId = $menu->id;
            $node->siteId = $siteId;

            if (!Craft::$app->getElements()->saveElement($node)) {
                throw new \RuntimeException(Craft::t('free-nav', 'Couldn’t save node “{title}”: {error}', [
                    'title' => $node->title,
                    'error' => implode(' ', $node->getFirstErrors()),
                ]));
            }

            if (!$nodes->placeNode($menu, $node, $parent)) {
                throw new \RuntimeException(Craft::t('free-nav', 'Couldn’t place node “{title}” in the menu.', ['title' => $node->title]));
            }

            $result->nodesImported++;
            $this->_write($menu, $entry['children'], $node, $result);
        }
    }

    /**
     * @return array{string, string} a handle and name no menu uses yet
     */
    private function _freeHandle(string $handle, string $name): array
    {
        $menus = FreeNav::getInstance()->getMenus();
        $candidate = $handle . '_imported';
        $i = 2;

        while ($menus->getMenuByHandle($candidate)) {
            $candidate = $handle . '_imported' . $i++;
        }

        return [$candidate, $name . ' (' . Craft::t('free-nav', 'Imported') . ')'];
    }

    private function _string(mixed $value): ?string
    {
        return is_scalar($value) && (string)$value !== '' ? (string)$value : null;
    }

    private function _array(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        return is_array($value) && $value !== [] ? $value : null;
    }
}
