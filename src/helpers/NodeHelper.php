<?php

namespace justinholt\freenav\helpers;

use justinholt\freenav\elements\Node;

class NodeHelper
{
    /**
     * Build a nested tree structure from a flat array of nodes.
     *
     * Nesting comes from the structure's `level`, which is the only source of truth for
     * node hierarchy, so the nodes must be in structure order (`lft` ascending) — which
     * is how NodeQuery returns them by default.
     *
     * Nodes whose parent is missing from $nodes (filtered out by status or a visibility
     * rule) are dropped along with their descendants, rather than being promoted up.
     */
    public static function buildTree(array $nodes): array
    {
        $byId = [];
        $rootIds = [];
        $childIds = [];
        $stack = []; // [id, level]
        $skipBelowLevel = null;

        foreach ($nodes as $node) {
            $level = $node->level ?? 1;

            // Inside a pruned subtree?
            if ($skipBelowLevel !== null) {
                if ($level > $skipBelowLevel) {
                    continue;
                }
                $skipBelowLevel = null;
            }

            while (!empty($stack) && $stack[count($stack) - 1][1] >= $level) {
                array_pop($stack);
            }

            $parentLevel = !empty($stack) ? $stack[count($stack) - 1][1] : 0;

            // The node's parent isn't in $nodes (filtered out by status or a visibility
            // rule), so drop the node and its descendants rather than promoting them
            if ($level > $parentLevel + 1) {
                $skipBelowLevel = $level;
                continue;
            }

            $byId[$node->id] = $node;

            if (!empty($stack)) {
                $childIds[$stack[count($stack) - 1][0]][] = $node->id;
            } else {
                $rootIds[] = $node->id;
            }

            $stack[] = [$node->id, $level];
        }

        return self::_assemble($rootIds, $byId, $childIds);
    }

    private static function _assemble(array $ids, array $byId, array $childIds): array
    {
        $items = [];

        foreach ($ids as $id) {
            $items[] = [
                'node' => $byId[$id],
                'children' => self::_assemble($childIds[$id] ?? [], $byId, $childIds),
            ];
        }

        return $items;
    }

    /**
     * Build a nodeId => parentId map from a flat array of nodes in structure order.
     */
    public static function buildParentMap(array $nodes): array
    {
        $map = [];
        $stack = []; // [id, level]

        foreach ($nodes as $node) {
            $level = $node->level ?? 1;

            while (!empty($stack) && $stack[count($stack) - 1][1] >= $level) {
                array_pop($stack);
            }

            if (!empty($stack)) {
                $map[$node->id] = $stack[count($stack) - 1][0];
            }

            $stack[] = [$node->id, $level];
        }

        return $map;
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
