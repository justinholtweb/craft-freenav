<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Services;

use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Structure\Enums\Mode;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Structures;
use CraftCms\Cms\User\Contracts\CraftUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Elements\Queries\NodeQuery;
use JustinHoltWeb\FreeNav\Enums\NodeType;
use JustinHoltWeb\FreeNav\Models\Menu;

class Nodes
{
    /**
     * Every node in a menu, once each, regardless of which sites it lives in.
     *
     * Management operations (export, delete, resave, counts) have to span sites: a menu enabled
     * only for a non-primary site has no nodes on the current site, so a site-scoped query
     * silently returns nothing. Rendering stays site-scoped.
     */
    public function findNodesInMenu(int $menuId): NodeQuery
    {
        return Node::find()
            ->menuId($menuId)
            ->siteId('*')
            ->unique()
            ->status(null)
            ->orderBy('lft');
    }

    /**
     * @return Node[]
     */
    public function getNodesByMenuId(int $menuId, ?int $siteId = null): array
    {
        return Node::find()
            ->menuId($menuId)
            ->siteId($siteId)
            ->status(null)
            ->orderBy('lft')
            ->all();
    }

    /**
     * Counts a menu's nodes the way the tree lists them: every node in the menu on one site,
     * disabled ones included.
     */
    public function getNodeCount(int $menuId, ?int $siteId = null): int
    {
        return Node::find()
            ->menuId($menuId)
            ->siteId($siteId)
            ->status(null)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function getNodesByMenuHandle(string $handle, array $criteria = []): NodeQuery
    {
        $query = Node::find()->menuHandle($handle);

        foreach ($criteria as $key => $value) {
            $query->$key = $value;
        }

        return $query;
    }

    /** @var array<int, bool>|null IDs of elements some node links to, for this request */
    private ?array $linkedElementIds = null;

    /** @var array<string, string> Titles linked elements had before the save in progress, by `id:siteId` */
    private array $previousTitles = [];

    /**
     * Notes a linked element's title before it's saved, so the sync afterwards can tell a node
     * that still shows the element's title from one an editor renamed.
     */
    public function rememberTitleBeforeSave(ElementInterface $element): void
    {
        if ($element instanceof Node || ! $element->id || ! $element->siteId || ElementHelper::isDraftOrRevision($element)) {
            return;
        }

        $this->linkedElementIds ??= DB::table('freenav_nodes')
            ->whereNotNull('linkedElementId')
            ->distinct()
            ->pluck('linkedElementId')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        if (! isset($this->linkedElementIds[$element->id])) {
            return;
        }

        $title = DB::table(Table::ELEMENTS_SITES)
            ->where('elementId', $element->id)
            ->where('siteId', $element->siteId)
            ->value('title');

        if (is_string($title)) {
            $this->previousTitles[$element->id.':'.$element->siteId] = $title;
        }
    }

    /**
     * Keeps linked nodes' titles in step with the element they link to, unless an editor gave
     * the node a title of its own.
     */
    public function syncNodeFromElement(ElementInterface $element): void
    {
        if ($element instanceof Node || ! $element->id || ElementHelper::isDraftOrRevision($element)) {
            return;
        }

        $key = $element->id.':'.$element->siteId;
        $previous = $this->previousTitles[$key] ?? null;
        unset($this->previousTitles[$key]);

        if ($previous === null || $previous === $element->title) {
            return;
        }

        $nodes = Node::find()
            ->linkedElementId($element->id)
            ->siteId($element->siteId)
            ->status(null)
            ->all();

        foreach ($nodes as $node) {
            // A node whose title differs from what the element was called has its own title.
            if ($node->title === $previous) {
                $node->title = (string) $element->title;
                Elements::saveElement($node, false);
            }
        }
    }

    /**
     * New links change which elements are worth watching.
     */
    public function forgetLinkedElementIds(): void
    {
        $this->linkedElementIds = null;
    }

    /**
     * A linked element was deleted: its nodes become custom URL nodes pointing at where it used
     * to be, or are disabled when it had no URL.
     */
    public function handleDeletedElement(ElementInterface $element): void
    {
        if ($element instanceof Node || ! $element->id || ElementHelper::isDraftOrRevision($element)) {
            return;
        }

        $nodes = Node::find()
            ->linkedElementId($element->id)
            ->siteId('*')
            ->unique()
            ->status(null)
            ->all();

        foreach ($nodes as $node) {
            $url = $element->getUrl();

            if ($url) {
                $node->nodeType = NodeType::Custom->value;
                $node->customUrl = $url;
            } else {
                $node->enabled = false;
            }

            $node->linkedElementId = null;
            Elements::saveElement($node, false);
        }
    }

    /**
     * Creates nodes in a menu from plain data — the add-node form, imports and the Verbb
     * migration all come through here.
     *
     * @param  array<int, array<string, mixed>>  $nodeDataArray
     * @return Node[]
     */
    public function addNodes(Menu $menu, array $nodeDataArray, bool $runValidation = true): array
    {
        $nodes = [];

        foreach ($nodeDataArray as $nodeData) {
            $node = $this->makeNode($menu, $nodeData);

            if (Elements::saveElement($node, $runValidation)) {
                $parent = $this->findNodeInMenu($menu, $nodeData['parentId'] ?? null, (int) $node->siteId);
                $this->placeNode($menu, $node, $parent);

                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * An unsaved node built from plain data.
     *
     * @param  array<string, mixed>  $nodeData
     */
    public function makeNode(Menu $menu, array $nodeData): Node
    {
        $node = new Node;
        $node->menuId = $menu->id;
        $node->title = (string) ($nodeData['title'] ?? '');
        $node->nodeType = (string) ($nodeData['nodeType'] ?? NodeType::Custom->value);
        $node->linkedElementId = isset($nodeData['linkedElementId']) && $nodeData['linkedElementId'] !== '' ? (int) $nodeData['linkedElementId'] : null;
        // 'url' is accepted for backwards compatibility with older exports
        $node->customUrl = $nodeData['customUrl'] ?? $nodeData['url'] ?? null;
        $node->classes = $nodeData['classes'] ?? null;
        $node->urlSuffix = $nodeData['urlSuffix'] ?? null;
        $node->customAttributes = $nodeData['customAttributes'] ?? null;
        $node->data = $nodeData['data'] ?? null;
        $node->newWindow = (bool) ($nodeData['newWindow'] ?? false);
        $node->icon = $nodeData['icon'] ?? null;
        $node->badge = $nodeData['badge'] ?? null;
        $node->visibilityRules = $nodeData['visibilityRules'] ?? null;
        $node->enabled = (bool) ($nodeData['enabled'] ?? true);

        // Nodes can only be saved to sites the menu is enabled for, so fall back to one of those
        // rather than whatever site the request is on.
        $siteId = isset($nodeData['siteId']) ? (int) $nodeData['siteId'] : null;
        $node->siteId = $siteId && $menu->isEnabledForSite($siteId) ? $siteId : $this->getDefaultSiteId($menu);

        // Only an element of the class the node type links to.
        $elementType = NodeType::tryFrom($node->nodeType)?->elementType();

        if (! $elementType) {
            $node->linkedElementId = null;
        } elseif ($node->linkedElementId && $node->title === '') {
            $element = Elements::getElementById($node->linkedElementId, $elementType, $node->siteId);
            $node->title = (string) ($element->title ?? '');
        }

        return $node;
    }

    /**
     * The element a node of `$type` may link to: only an element of that type's class, on the
     * node's site, enabled or not — and, when `$user` is given, only one that user may view, so a
     * menu editor can't link to (and copy the title of) an entry or asset they can't see by
     * posting its ID.
     */
    public function linkableElement(NodeType $type, int $elementId, int $siteId, ?CraftUser $user): ?ElementInterface
    {
        $elementType = $type->elementType();

        if (! $elementType || $elementId <= 0) {
            return null;
        }

        $element = $elementType::find()->id($elementId)->siteId($siteId)->status(null)->one();

        if (! $element instanceof ElementInterface) {
            return null;
        }

        return $user === null || $user->can('view', $element) ? $element : null;
    }

    /**
     * Places a newly created node in its menu's structure, under $parent if given.
     */
    public function placeNode(Menu $menu, Node $node, ?Node $parent = null): bool
    {
        if (! $menu->structureId) {
            return false;
        }

        if ($parent) {
            return Structures::append($menu->structureId, $node, $parent, Mode::Insert);
        }

        if ($menu->defaultPlacement === 'beginning') {
            return Structures::prependToRoot($menu->structureId, $node, Mode::Insert);
        }

        return Structures::appendToRoot($menu->structureId, $node, Mode::Insert);
    }

    /**
     * Moves a node within its menu's structure, under $parent and after $prevSibling.
     */
    public function moveNode(Menu $menu, Node $node, ?Node $parent = null, ?Node $prevSibling = null): bool
    {
        if (! $menu->structureId) {
            return false;
        }

        if ($prevSibling) {
            return Structures::moveAfter($menu->structureId, $node, $prevSibling);
        }

        if ($parent) {
            return Structures::prepend($menu->structureId, $node, $parent);
        }

        return Structures::prependToRoot($menu->structureId, $node);
    }

    /**
     * Resolves a node ID to a node in the given menu.
     *
     * Node hierarchy is site-agnostic (it lives in the menu's structure), but a node only has rows
     * for the sites its menu propagates to. Looking one up against the current site would miss
     * nodes that live on another site, so search all sites and prefer $preferSiteId.
     */
    public function findNodeInMenu(Menu $menu, mixed $nodeId, ?int $preferSiteId = null): ?Node
    {
        if (! $nodeId) {
            return null;
        }

        $query = Node::find()
            ->id($nodeId)
            ->menuId($menu->id)
            ->siteId('*')
            ->unique()
            ->status(null);

        if ($preferSiteId) {
            $query->preferSites([$preferSiteId]);
        }

        $node = $query->one();

        if (! $node) {
            Log::warning("FreeNav: node {$nodeId} was not found in menu “{$menu->handle}”.");
        }

        return $node;
    }

    /**
     * The site a new node should be created on: the current site when the menu is enabled for it,
     * otherwise the menu's first enabled site.
     */
    public function getDefaultSiteId(Menu $menu): int
    {
        $currentSiteId = (int) Sites::getCurrentSite()->id;

        if ($menu->isEnabledForSite($currentSiteId)) {
            return $currentSiteId;
        }

        $enabledSiteIds = $menu->getEnabledSiteIds();

        return (int) reset($enabledSiteIds);
    }
}
