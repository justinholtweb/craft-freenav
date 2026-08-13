<?php

namespace justinholt\freenav\services;

use Craft;
use craft\base\Element;
use justinholt\freenav\elements\db\NodeQuery;
use justinholt\freenav\elements\Node;
use justinholt\freenav\enums\NodeType;
use justinholt\freenav\models\Menu;
use yii\base\Component;

class Nodes extends Component
{
    /**
     * Every node in a menu, once each, regardless of which sites it lives in.
     *
     * Management operations (export, delete, resave, counts) have to span sites: a
     * menu enabled only for a non-primary site has no nodes on the current site, so a
     * site-scoped query silently returns nothing. Rendering stays site-scoped.
     */
    public function findNodesInMenu(int $menuId): NodeQuery
    {
        return Node::find()
            ->menuId($menuId)
            ->siteId('*')
            ->unique()
            ->status(null)
            ->orderBy(['lft' => SORT_ASC]);
    }

    public function getNodesByMenuId(int $menuId, ?int $siteId = null): array
    {
        return Node::find()
            ->menuId($menuId)
            ->siteId($siteId)
            ->status(null)
            ->orderBy(['lft' => SORT_ASC])
            ->all();
    }

    public function getNodesByMenuHandle(string $handle, array $criteria = []): NodeQuery
    {
        $query = Node::find()
            ->menuHandle($handle);

        foreach ($criteria as $key => $value) {
            if (method_exists($query, $key)) {
                $query->$key($value);
            } else {
                $query->$key = $value;
            }
        }

        return $query;
    }

    public function syncNodeFromElement(Element $element): void
    {
        $nodes = Node::find()
            ->linkedElementId($element->id)
            ->siteId($element->siteId)
            ->status(null)
            ->all();

        if (empty($nodes)) {
            return;
        }

        foreach ($nodes as $node) {
            $changed = false;

            // Sync title if not overridden
            if (!$node->hasOverriddenTitle() && $node->title !== $element->title) {
                $node->title = $element->title;
                $changed = true;
            }

            if ($changed) {
                Craft::$app->getElements()->saveElement($node, false);
            }
        }
    }

    public function handleDeletedElement(Element $element): void
    {
        $nodes = Node::find()
            ->linkedElementId($element->id)
            ->siteId('*')
            ->unique()
            ->status(null)
            ->all();

        foreach ($nodes as $node) {
            // Convert to custom URL node with cached URL, or disable
            $url = $element->getUrl();

            if ($url) {
                $node->nodeType = NodeType::Custom->value;
                $node->customUrl = $url;
                $node->linkedElementId = null;
                Craft::$app->getElements()->saveElement($node, false);
            } else {
                $node->enabled = false;
                $node->linkedElementId = null;
                Craft::$app->getElements()->saveElement($node, false);
            }
        }
    }

    public function getParentOptions(Menu $menu, ?Node $exclude = null, ?int $siteId = null): array
    {
        $options = [
            ['label' => '—', 'value' => ''],
        ];

        $nodes = Node::find()
            ->menuId($menu->id)
            ->siteId($siteId)
            ->status(null)
            ->orderBy(['lft' => SORT_ASC])
            ->all();

        foreach ($nodes as $node) {
            if ($exclude && $node->id === $exclude->id) {
                continue;
            }

            $prefix = str_repeat('    ', max(0, $node->level - 1));
            $options[] = [
                'label' => $prefix . $node->title,
                'value' => $node->id,
            ];
        }

        return $options;
    }

    public function addNodes(Menu $menu, array $nodeDataArray): array
    {
        $nodes = [];

        foreach ($nodeDataArray as $nodeData) {
            $node = new Node();
            $node->menuId = $menu->id;
            $node->title = $nodeData['title'] ?? '';
            $node->nodeType = $nodeData['nodeType'] ?? NodeType::Custom->value;
            $node->linkedElementId = $nodeData['linkedElementId'] ?? null;
            // 'url' is accepted for backwards compatibility with older exports
            $node->customUrl = $nodeData['customUrl'] ?? $nodeData['url'] ?? null;
            $node->classes = $nodeData['classes'] ?? null;
            $node->urlSuffix = $nodeData['urlSuffix'] ?? null;
            $node->customAttributes = $nodeData['customAttributes'] ?? null;
            $node->data = $nodeData['data'] ?? null;
            $node->newWindow = $nodeData['newWindow'] ?? false;
            $node->icon = $nodeData['icon'] ?? null;
            $node->badge = $nodeData['badge'] ?? null;
            $node->visibilityRules = $nodeData['visibilityRules'] ?? null;
            $node->enabled = $nodeData['enabled'] ?? true;

            // Set site. Nodes can only be saved to sites the menu is enabled for, so
            // fall back to one of those rather than whatever site the request is on.
            $node->siteId = $nodeData['siteId'] ?? $this->getDefaultSiteId($menu);

            // If element-linked, auto-set title from element
            if ($node->linkedElementId && empty($node->title)) {
                $nodeTypeEnum = NodeType::tryFrom($node->nodeType);
                if ($nodeTypeEnum && $nodeTypeEnum->elementType()) {
                    $element = Craft::$app->getElements()->getElementById(
                        $node->linkedElementId,
                        $nodeTypeEnum->elementType(),
                        $node->siteId,
                    );
                    if ($element) {
                        $node->title = $element->title ?? '';
                    }
                }
            }

            if (Craft::$app->getElements()->saveElement($node)) {
                $parent = $this->findNodeInMenu($menu, $nodeData['parentId'] ?? null, $node->siteId);
                $this->placeNode($menu, $node, $parent);

                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * Places a newly created node in its menu's structure, under $parent if given.
     */
    public function placeNode(Menu $menu, Node $node, ?Node $parent = null): bool
    {
        if (!$menu->structureId) {
            return false;
        }

        $structures = Craft::$app->getStructures();

        if ($parent) {
            return $structures->append($menu->structureId, $node, $parent);
        }

        if ($menu->defaultPlacement === 'beginning') {
            return $structures->prependToRoot($menu->structureId, $node);
        }

        return $structures->appendToRoot($menu->structureId, $node);
    }

    /**
     * Moves a node within its menu's structure, under $parent and after $prevSibling.
     */
    public function moveNode(Menu $menu, Node $node, ?Node $parent = null, ?Node $prevSibling = null): bool
    {
        if (!$menu->structureId) {
            return false;
        }

        $structures = Craft::$app->getStructures();

        if ($prevSibling) {
            return $structures->moveAfter($menu->structureId, $node, $prevSibling);
        }

        if ($parent) {
            return $structures->prepend($menu->structureId, $node, $parent);
        }

        return $structures->prependToRoot($menu->structureId, $node);
    }

    /**
     * Resolves a node ID to a node in the given menu.
     *
     * Node hierarchy is site-agnostic (it lives in the menu's structure), but a node
     * only has rows for the sites its menu propagates to. Looking one up against the
     * current site would miss nodes that live on another site, so search all sites and
     * prefer $preferSiteId.
     */
    public function findNodeInMenu(Menu $menu, mixed $nodeId, ?int $preferSiteId = null): ?Node
    {
        if (!$nodeId) {
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

        if (!$node) {
            Craft::warning(
                "Node $nodeId was not found in menu \"$menu->handle\".",
                __METHOD__,
            );
        }

        return $node;
    }

    /**
     * The site a new node should be created on: the current site when the menu is
     * enabled for it, otherwise the menu's first enabled site.
     */
    public function getDefaultSiteId(Menu $menu): int
    {
        $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;

        if ($menu->isEnabledForSite($currentSiteId)) {
            return $currentSiteId;
        }

        $enabledSiteIds = $menu->getEnabledSiteIds();

        return reset($enabledSiteIds);
    }
}
