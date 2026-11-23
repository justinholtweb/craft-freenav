<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Tests\Unit;

use JustinHoltWeb\FreeNav\Helpers\NodeHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests the tree building in NodeHelper, which nests nodes by their structure `level`
 * rather than by a stored parent ID. Uses lightweight stand-ins for Node elements so
 * the suite stays independent of a running Craft application.
 */
final class NodeHelperTest extends TestCase
{
    /**
     * @param  array<int, array{0: int, 1: int}>  $idsAndLevels
     * @return object[]
     */
    private function nodes(array $idsAndLevels): array
    {
        return array_map(fn (array $pair) => new class($pair[0], $pair[1])
        {
            public function __construct(public int $id, public int $level) {}
        }, $idsAndLevels);
    }

    /**
     * @param  array<int, mixed>  $tree
     * @return array<int, mixed>
     */
    private function idTree(array $tree): array
    {
        return array_map(fn (array $item) => [
            'id' => $item['node']->id,
            'children' => $this->idTree($item['children']),
        ], $tree);
    }

    public function test_flat_list_becomes_all_roots(): void
    {
        $tree = NodeHelper::buildTree($this->nodes([[1, 1], [2, 1], [3, 1]]));

        self::assertCount(3, $tree);
        self::assertSame([1, 2, 3], array_map(fn (array $item) => $item['node']->id, $tree));
        self::assertSame([[], [], []], array_map(fn (array $item) => $item['children'], $tree));
    }

    public function test_nests_by_level(): void
    {
        // 1
        //   2
        //     3
        //   4
        // 5
        $tree = NodeHelper::buildTree($this->nodes([[1, 1], [2, 2], [3, 3], [4, 2], [5, 1]]));

        self::assertSame([
            [
                'id' => 1,
                'children' => [
                    ['id' => 2, 'children' => [['id' => 3, 'children' => []]]],
                    ['id' => 4, 'children' => []],
                ],
            ],
            ['id' => 5, 'children' => []],
        ], $this->idTree($tree));
    }

    public function test_drops_nodes_whose_parent_is_missing(): void
    {
        // Node 2 (level 2) was filtered out, so its children must go with it
        // rather than being promoted alongside node 1.
        $tree = NodeHelper::buildTree($this->nodes([[1, 1], [3, 3], [4, 4], [5, 1]]));

        self::assertSame([
            ['id' => 1, 'children' => []],
            ['id' => 5, 'children' => []],
        ], $this->idTree($tree));
    }

    public function test_drops_leading_nodes_with_no_parent(): void
    {
        $tree = NodeHelper::buildTree($this->nodes([[1, 2], [2, 1]]));

        self::assertSame([['id' => 2, 'children' => []]], $this->idTree($tree));
    }

    public function test_resumes_after_a_pruned_subtree(): void
    {
        // 1, [2 pruned + its child 3], then 4 is a valid child of 1 again
        $tree = NodeHelper::buildTree($this->nodes([[1, 1], [2, 3], [3, 4], [4, 2]]));

        self::assertSame([
            ['id' => 1, 'children' => [['id' => 4, 'children' => []]]],
        ], $this->idTree($tree));
    }

    public function test_build_parent_map(): void
    {
        $map = NodeHelper::buildParentMap($this->nodes([[1, 1], [2, 2], [3, 3], [4, 2], [5, 1]]));

        self::assertSame([2 => 1, 3 => 2, 4 => 1], $map);
    }

    public function test_flatten_tree_restores_structure_order(): void
    {
        $nodes = $this->nodes([[1, 1], [2, 2], [3, 3], [4, 2], [5, 1]]);

        $flattened = NodeHelper::flattenTree(NodeHelper::buildTree($nodes));

        self::assertSame([1, 2, 3, 4, 5], array_map(fn (object $node) => $node->id, $flattened));
    }

    public function test_flatten_tree_drops_pruned_subtrees(): void
    {
        $nodes = $this->nodes([[1, 1], [2, 3], [3, 4], [4, 2]]);

        $flattened = NodeHelper::flattenTree(NodeHelper::buildTree($nodes));

        self::assertSame([1, 4], array_map(fn (object $node) => $node->id, $flattened));
    }
}
