<?php

namespace justinholt\freenav\helpers;

use justinholt\freenav\elements\Node;

class NodeHelper
{
    /**
     * Build a nested tree structure from a flat array of nodes.
     */
    public static function buildTree(array $nodes): array
    {
        $tree = [];
        $map = [];

        foreach ($nodes as $node) {
            $map[$node->id] = [
                'node' => $node,
                'children' => [],
            ];
        }

        foreach ($nodes as $node) {
            if ($node->level > 1 && $node->parentId && isset($map[$node->parentId])) {
                $map[$node->parentId]['children'][] = &$map[$node->id];
            } else {
                $tree[] = &$map[$node->id];
            }
        }

        return $tree;
    }

    /**
     * Flatten a tree back to a linear array.
     */
    public static function flattenTree(array $tree): array
    {
        $flat = [];

        foreach ($tree as $item) {
            $flat[] = $item['node'];
            if (!empty($item['children'])) {
                $flat = array_merge($flat, self::flattenTree($item['children']));
            }
        }

        return $flat;
    }

    /**
     * Filter nodes by visibility rules.
     */
    public static function filterVisible(array $nodes): array
    {
        return array_values(array_filter($nodes, fn(Node $node) => $node->isVisible()));
    }
}
