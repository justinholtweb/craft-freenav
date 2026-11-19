<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Services;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\User\Contracts\CraftUser;
use Illuminate\Support\Facades\DB;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Enums\NodeType;
use JustinHoltWeb\FreeNav\Models\ImportResult;
use JustinHoltWeb\FreeNav\Models\Menu;
use RuntimeException;
use Throwable;

use function CraftCms\Cms\t;

/**
 * Menu JSON export and import, shared by the control panel and the console.
 *
 * A menu's *definition* is project config, its *nodes* are content. So importing nodes into a
 * menu that already exists (`replace`) works anywhere, while an import that has to create the
 * menu needs `allowAdminChanges`, the same as creating one in the control panel.
 *
 * Files exported by FreeNav 5 import unchanged: their Craft 5 element class names are mapped to
 * Craft 6's, and category links are looked up as entries (Craft's category-to-entry conversion
 * keeps each element's ID and UID).
 */
class MenuTransfer
{
    public const string FORMAT_VERSION = '1.2.0';

    /** Deeper than any real menu; stops a hand-edited file recursing without end. */
    private const int MAX_DEPTH = 32;

    /**
     * The element classes a FreeNav 5 export may name, mapped to the node type that links to the
     * same elements on Craft 6.
     */
    private const array LEGACY_ELEMENT_TYPES = [
        'craft\\elements\\Entry' => NodeType::Entry,
        'craft\\elements\\Category' => NodeType::Entry,
        'craft\\elements\\Asset' => NodeType::Asset,
        'craft\\commerce\\elements\\Product' => NodeType::Product,
    ];

    /** Whose access decides which linked elements an import may keep. */
    private ?CraftUser $user = null;

    public function __construct(
        private readonly Menus $menus,
        private readonly Nodes $nodes,
        private readonly MenuCache $menuCache,
    ) {}

    /**
     * @return array<string, mixed>
     */
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
            'nodes' => $this->exportTree($this->nodes->findNodesInMenu((int) $menu->id)->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $export
     */
    public function encode(array $export): string
    {
        return Json::encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * Decodes an export file, or returns null when it isn't one.
     *
     * @return array<string, mixed>|null
     */
    public function decode(string $json): ?array
    {
        $data = Json::decodeIfJson($json);

        if (
            ! is_array($data)
            || ! isset($data['freeNav'], $data['menu'], $data['nodes'])
            || ! is_array($data['menu'])
            || ! is_array($data['nodes'])
            || ! is_string($data['menu']['handle'] ?? null)
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
     * Custom attributes with unsafe names or non-text values are dropped.
     *
     * With `$user` (a control panel import), element links are only kept to elements that user
     * may view. The console has no user, and its imports trust the file the way a deploy script
     * is trusted.
     *
     * @param  array<string, mixed>  $data
     */
    public function importMenu(array $data, ?string $handle = null, bool $replace = false, bool $renameOnConflict = false, ?CraftUser $user = null): ImportResult
    {
        $this->user = $user;

        $result = new ImportResult;
        $menuData = $data['menu'];
        $handle = $handle ?: (string) $menuData['handle'];
        $name = is_string($menuData['name'] ?? null) && $menuData['name'] !== '' ? $menuData['name'] : $handle;

        $menu = $this->menus->getMenuByHandle($handle);

        if ($menu && $renameOnConflict) {
            [$handle, $name] = $this->freeHandle($handle, $name);
            $menu = null;
        } elseif ($menu && ! $replace) {
            $result->menu = $menu;
            $result->errors[] = t('A menu with the handle “{handle}” already exists. Replace its nodes, or import under another handle.', ['handle' => $handle], category: 'free-nav');

            return $result;
        }

        if (! $menu && ! $this->menus->allowAdminChanges()) {
            $result->errors[] = t('There is no menu with the handle “{handle}”, and menus can only be created where admin changes are allowed. Create the menu there first (project config will bring it here), then import its nodes.', ['handle' => $handle], category: 'free-nav');

            return $result;
        }

        // Settings the new menu would get, so the limits can be checked before it exists.
        $limits = $menu ?? new Menu([
            'maxNodes' => is_numeric($menuData['maxNodes'] ?? null) ? (int) $menuData['maxNodes'] : null,
            'maxLevels' => is_numeric($menuData['maxLevels'] ?? null) ? (int) $menuData['maxLevels'] : null,
        ]);

        $plan = $this->plan($data['nodes'], $menu, $result);
        $this->checkLimits($plan, $limits, $result);

        if (! $result->getSuccess()) {
            return $result;
        }

        if (! $menu) {
            $menu = new Menu;
            $menu->name = $name;
            $menu->handle = $handle;
            $menu->propagationMethod = is_string($menuData['propagationMethod'] ?? null) ? $menuData['propagationMethod'] : 'all';
            $menu->maxNodes = $limits->maxNodes;
            $menu->maxLevels = $limits->maxLevels;
            $menu->setSiteSettings($this->menus->allSitesSettings());

            if (! $this->menus->saveMenu($menu)) {
                foreach ($menu->getFirstErrors() as $error) {
                    $result->errors[] = (string) $error;
                }

                return $result;
            }

            $menu = $this->menus->getMenuById((int) $menu->id) ?? $menu;
            $result->createdMenu = true;
        }

        $result->menu = $menu;

        try {
            DB::transaction(function () use ($menu, $replace, $plan, $result) {
                if ($replace && ! $result->createdMenu) {
                    // Deepest first, so no node outlives its parent mid-way.
                    $existing = array_reverse($this->nodes->findNodesInMenu((int) $menu->id)->all());

                    foreach ($existing as $node) {
                        if (! Elements::deleteElement($node)) {
                            throw new RuntimeException("Couldn't remove node #{$node->id}.");
                        }

                        $result->nodesRemoved++;
                    }
                }

                $this->write($menu, $plan, null, $result);
            });
        } catch (Throwable $e) {
            $result->nodesImported = 0;
            $result->nodesRemoved = 0;
            $result->errors[] = $e->getMessage();

            // All or nothing: don't leave behind an empty menu the import made.
            if ($result->createdMenu) {
                $this->menus->deleteMenu($menu);
                $result->menu = null;
                $result->createdMenu = false;
            }
        }

        $this->menuCache->invalidate((string) $menu->handle);

        return $result;
    }

    /**
     * Nodes come back in structure order, so each node's children are the run of deeper nodes
     * straight after it.
     *
     * @param  Node[]  $nodes
     * @return list<array<string, mixed>>
     */
    private function exportTree(array $nodes, int &$i = 0, int $level = 1): array
    {
        $items = [];

        while ($i < count($nodes)) {
            $node = $nodes[$i];
            $nodeLevel = (int) ($node->level ?? 1);

            if ($nodeLevel < $level) {
                break;
            }

            $i++;
            $item = $this->exportNode($node);
            $children = $this->exportTree($nodes, $i, $nodeLevel + 1);

            if ($children !== []) {
                $item['children'] = $children;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportNode(Node $node): array
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
     * @param  array<mixed>  $items
     * @return list<array{node: Node, children: array<mixed>}>
     */
    private function plan(array $items, ?Menu $menu, ImportResult $result, int $depth = 1, string $path = ''): array
    {
        $plan = [];

        if ($depth > self::MAX_DEPTH) {
            $result->errors[] = t('Nodes are nested more than {max} levels deep.', ['max' => self::MAX_DEPTH], category: 'free-nav');

            return $plan;
        }

        foreach (array_values($items) as $i => $item) {
            $label = ltrim($path.'/'.($i + 1), '/');

            if (! is_array($item)) {
                $result->errors[] = t('Node {path} is not an object.', ['path' => $label], category: 'free-nav');

                continue;
            }

            $typeValue = is_scalar($item['nodeType'] ?? null) ? (string) $item['nodeType'] : NodeType::Custom->value;
            $type = NodeType::tryFrom($typeValue);

            if (! $type) {
                $result->errors[] = t('Node {path} has an unknown type “{type}”.', ['path' => $label, 'type' => $typeValue], category: 'free-nav');

                continue;
            }

            // Craft 6 has no categories; Craft's conversion turned them into entries.
            if ($type === NodeType::Category) {
                $type = NodeType::Entry;
            }

            $node = new Node;
            $node->menuId = $menu->id ?? 0;
            $node->title = is_scalar($item['title'] ?? null) ? trim((string) $item['title']) : '';
            $node->nodeType = $type->value;
            // 'url' is accepted for backwards compatibility with older exports
            $node->customUrl = $this->string($item['customUrl'] ?? $item['url'] ?? null);
            $node->classes = $this->string($item['classes'] ?? null);
            $node->urlSuffix = $this->string($item['urlSuffix'] ?? null);
            $node->customAttributes = Node::safeCustomAttributes($this->array($item['customAttributes'] ?? null));
            $node->data = $this->array($item['data'] ?? null);
            $node->visibilityRules = $this->array($item['visibilityRules'] ?? null);
            $node->newWindow = (bool) ($item['newWindow'] ?? false);
            $node->icon = $this->string($item['icon'] ?? null);
            $node->badge = $this->string($item['badge'] ?? null);
            $node->enabled = (bool) ($item['enabled'] ?? true);

            $element = $this->linkedElement($type, $item);

            if ($element) {
                $node->linkedElementId = $element->id;

                if ($node->title === '') {
                    $node->title = (string) ($element->title ?? '');
                }
            } elseif ($type->isElement()) {
                $result->warnings[] = t('Node {path} (“{title}”) links to a {type} that doesn’t exist here or that you can’t view; it will have no link until one is chosen.', [
                    'path' => $label,
                    'title' => $node->title,
                    'type' => strtolower($type->label()),
                ], category: 'free-nav');
            }

            if ($node->title === '') {
                $result->errors[] = t('Node {path} has no title.', ['path' => $label], category: 'free-nav');
            }

            if (! $node->validate(['customUrl', 'urlSuffix'])) {
                $result->errors[] = t('Node {path} (“{title}”): {error}', [
                    'path' => $label,
                    'title' => $node->title,
                    'error' => $node->errors()->first('customUrl') ?: $node->errors()->first('urlSuffix'),
                ], category: 'free-nav');
            }

            $children = isset($item['children']) && is_array($item['children'])
                ? $this->plan($item['children'], $menu, $result, $depth + 1, $label)
                : [];

            $plan[] = ['node' => $node, 'children' => $children];
        }

        return $plan;
    }

    /**
     * Only an element of the class the node type links to — the file names the class, and that
     * must never reach an arbitrary class's `find()`.
     *
     * @param  array<mixed>  $item
     */
    private function linkedElement(NodeType $type, array $item): ?ElementInterface
    {
        $elementType = $type->elementType();
        $uid = $item['linkedElementUid'] ?? null;

        if (! $elementType || ! is_string($uid) || $uid === '' || ! class_exists($elementType)) {
            return null;
        }

        $named = $item['linkedElementType'] ?? null;

        if ($named !== null && $named !== $elementType && (self::LEGACY_ELEMENT_TYPES[$named] ?? null) !== $type) {
            return null;
        }

        $element = $elementType::find()->uid($uid)->status(null)->site('*')->unique()->one();

        if ($element && $this->user && ! $this->user->can('view', $element)) {
            return null;
        }

        return $element;
    }

    /**
     * @param  list<array{node: Node, children: array<mixed>}>  $plan
     */
    private function checkLimits(array $plan, Menu $menu, ImportResult $result): void
    {
        [$count, $depth] = $this->measure($plan);

        if ($menu->maxNodes && $count > $menu->maxNodes) {
            $result->errors[] = t('The file has {count} nodes; the menu allows {max}.', ['count' => $count, 'max' => $menu->maxNodes], category: 'free-nav');
        }

        if ($menu->maxLevels && $depth > $menu->maxLevels) {
            $result->errors[] = t('The file nests nodes {depth} levels deep; the menu allows {max}.', ['depth' => $depth, 'max' => $menu->maxLevels], category: 'free-nav');
        }
    }

    /**
     * @param  array<mixed>  $plan
     * @return array{int, int} node count, deepest level
     */
    private function measure(array $plan, int $level = 1): array
    {
        $count = 0;
        $depth = $plan ? $level : 0;

        foreach ($plan as $entry) {
            [$c, $d] = $this->measure($entry['children'], $level + 1);
            $count += 1 + $c;
            $depth = max($depth, $d);
        }

        return [$count, $depth];
    }

    /**
     * @param  array<mixed>  $plan
     */
    private function write(Menu $menu, array $plan, ?Node $parent, ImportResult $result): void
    {
        $siteId = $this->nodes->getDefaultSiteId($menu);

        foreach ($plan as $entry) {
            /** @var Node $node */
            $node = $entry['node'];
            $node->menuId = $menu->id;
            $node->siteId = $siteId;

            if (! Elements::saveElement($node)) {
                throw new RuntimeException(t('Couldn’t save node “{title}”: {error}', [
                    'title' => $node->title,
                    'error' => implode(' ', $node->getFirstErrors()),
                ], category: 'free-nav'));
            }

            if (! $this->nodes->placeNode($menu, $node, $parent)) {
                throw new RuntimeException(t('Couldn’t place node “{title}” in the menu.', ['title' => $node->title], category: 'free-nav'));
            }

            $result->nodesImported++;
            $this->write($menu, $entry['children'], $node, $result);
        }
    }

    /**
     * @return array{string, string} a handle and name no menu uses yet
     */
    private function freeHandle(string $handle, string $name): array
    {
        $candidate = $handle.'_imported';
        $i = 2;

        while ($this->menus->getMenuByHandle($candidate)) {
            $candidate = $handle.'_imported'.$i++;
        }

        return [$candidate, $name.' ('.t('Imported', category: 'free-nav').')'];
    }

    private function string(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @return array<mixed>|null
     */
    private function array(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        return is_array($value) && $value !== [] ? $value : null;
    }
}
