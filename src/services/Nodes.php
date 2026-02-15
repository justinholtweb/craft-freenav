<?php

namespace justinholt\freenav\services;

use Craft;
use craft\base\Element;
use craft\helpers\Json;
use justinholt\freenav\elements\db\NodeQuery;
use justinholt\freenav\elements\Node;
use justinholt\freenav\enums\NodeType;
use justinholt\freenav\FreeNav;
use justinholt\freenav\models\Menu;
use yii\base\Component;

class Nodes extends Component
{
    public function getNodesByMenuId(int $menuId): array
    {
        return Node::find()
            ->menuId($menuId)
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
            ->status(null)
            ->all();

        foreach ($nodes as $node) {
            // Convert to custom URL node with cached URL, or disable
            $url = $element->getUrl();

            if ($url) {
                $node->nodeType = NodeType::Custom->value;
                $node->url = $url;
                $node->linkedElementId = null;
                Craft::$app->getElements()->saveElement($node, false);
            } else {
                $node->enabled = false;
                $node->linkedElementId = null;
                Craft::$app->getElements()->saveElement($node, false);
            }
        }
    }

    public function getParentOptions(Menu $menu, ?Node $exclude = null): array
    {
        $options = [
            ['label' => '—', 'value' => ''],
        ];

        $nodes = Node::find()
            ->menuId($menu->id)
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
            $node->url = $nodeData['url'] ?? null;
            $node->classes = $nodeData['classes'] ?? null;
            $node->urlSuffix = $nodeData['urlSuffix'] ?? null;
            $node->customAttributes = $nodeData['customAttributes'] ?? null;
            $node->data = $nodeData['data'] ?? null;
            $node->newWindow = $nodeData['newWindow'] ?? false;
            $node->icon = $nodeData['icon'] ?? null;
            $node->badge = $nodeData['badge'] ?? null;
            $node->visibilityRules = $nodeData['visibilityRules'] ?? null;
            $node->enabled = $nodeData['enabled'] ?? true;

            // Set site
            $node->siteId = $nodeData['siteId'] ?? Craft::$app->getSites()->getCurrentSite()->id;

            // If element-linked, auto-set title from element
            if ($node->linkedElementId && empty($node->title)) {
                $nodeTypeEnum = NodeType::tryFrom($node->nodeType);
                if ($nodeTypeEnum && $nodeTypeEnum->elementType()) {
                    $element = Craft::$app->getElements()->getElementById(
                        $node->linkedElementId,
                        $nodeTypeEnum->elementType(),
                        $node->siteId
                    );
                    if ($element) {
                        $node->title = $element->title ?? '';
                    }
                }
            }

            if (Craft::$app->getElements()->saveElement($node)) {
                // Add to structure
                $structure = Craft::$app->getStructures()->getStructureById($menu->structureId);
                if ($structure) {
                    $parentId = $nodeData['parentId'] ?? null;
                    if ($parentId) {
                        $parentNode = Craft::$app->getElements()->getElementById($parentId);
                        if ($parentNode) {
                            Craft::$app->getStructures()->append($structure->id, $node, $parentNode);
                        } else {
                            Craft::$app->getStructures()->appendToRoot($structure->id, $node);
                        }
                    } else {
                        if ($menu->defaultPlacement === 'beginning') {
                            Craft::$app->getStructures()->prependToRoot($structure->id, $node);
                        } else {
                            Craft::$app->getStructures()->appendToRoot($structure->id, $node);
                        }
                    }
                }

                $nodes[] = $node;
            }
        }

        return $nodes;
    }
}
