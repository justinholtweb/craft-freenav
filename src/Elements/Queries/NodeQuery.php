<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Elements\Queries;

use CraftCms\Cms\Element\Enums\ElementQueryParamType;
use CraftCms\Cms\Element\Queries\Attributes\ElementQueryParam;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Enums\NodeType;
use JustinHoltWeb\FreeNav\Services\Menus;
use Override;

/**
 * @extends ElementQuery<Node>
 */
class NodeQuery extends ElementQuery
{
    /**
     * Nodes always live in their menu's structure, so the structure data is joined even when the
     * query doesn't narrow to one menu. Without it a bare `Node::find()->id(…)` comes back with no
     * level and no way to tell where the node sits.
     */
    #[Override]
    public bool $withStructure {
        get {
            if (! isset($this->withStructure)) {
                $this->withStructure = ! $this->trashed;
            }

            return $this->withStructure;
        }
    }

    #[Override]
    protected string $table = 'freenav_nodes';

    public mixed $menuId = null;

    public mixed $menuHandle = null;

    public mixed $nodeType = null;

    public ?bool $hasUrl = null;

    public mixed $linkedElementId = null;

    public ?bool $visibleOnly = null;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct(Node::class, $config);

        $this->query->addSelect([
            'freenav_nodes.menuId as menuId',
            'freenav_nodes.linkedElementId as linkedElementId',
            'freenav_nodes.nodeType as nodeType',
            'freenav_nodes.customUrl as customUrl',
            'freenav_nodes.classes as classes',
            'freenav_nodes.urlSuffix as urlSuffix',
            'freenav_nodes.customAttributes as customAttributes',
            'freenav_nodes.data as data',
            'freenav_nodes.newWindow as newWindow',
            'freenav_nodes.icon as icon',
            'freenav_nodes.badge as badge',
            'freenav_nodes.visibilityRules as visibilityRules',
            'freenav_nodes.deletedWithMenu as deletedWithMenu',
        ]);

        // Runs before Craft applies the structure params, so setting `structureId` here is early
        // enough for `level`, `descendantOf` and the rest.
        $this->beforeQuery(static function (self $query) {
            if ($query->menuHandle !== null) {
                $ids = DB::table('freenav_menus')
                    ->whereParam('handle', $query->menuHandle)
                    ->whereNull('dateDeleted')
                    ->pluck('id')
                    ->all();

                if ($ids === []) {
                    throw new QueryAbortedException;
                }

                $query->whereIn('freenav_nodes.menuId', $ids);
                self::useMenuStructure($query, $ids);
            }

            if ($query->menuId !== null) {
                $query->whereParam('freenav_nodes.menuId', $query->menuId);
                self::useMenuStructure($query, (array) $query->menuId);
            }

            static::applyNodeType($query, $query->nodeType);

            if ($query->hasUrl !== null) {
                $query->hasUrl
                    ? $query->where('freenav_nodes.nodeType', '!=', NodeType::Passive->value)
                    : $query->where('freenav_nodes.nodeType', NodeType::Passive->value);
            }

            if ($query->linkedElementId !== null) {
                $query->whereParam('freenav_nodes.linkedElementId', $query->linkedElementId);
            }
        });
    }

    /**
     * With one menu in play, its structure is the one to order and filter by (like a section's).
     *
     * @param  array<mixed>  $menuIds
     */
    private static function useMenuStructure(self $query, array $menuIds): void
    {
        if ($query->structureId || count($menuIds) !== 1) {
            return;
        }

        $menuId = reset($menuIds);

        if (is_numeric($menuId)) {
            $query->structureId = app(Menus::class)->getMenuById((int) $menuId)?->structureId;
        }
    }

    public static function applyNodeType(Builder $query, mixed $value): void
    {
        if ($value instanceof NodeType) {
            $value = $value->value;
        }

        if ($value !== null) {
            $query->whereParam('freenav_nodes.nodeType', $value);
        }
    }

    #[ElementQueryParam(ElementQueryParamType::IntOrInts, 'Narrows the query results based on the menus the nodes belong to, per the menus’ IDs.')]
    public function menuId(mixed $value): static
    {
        $this->menuId = $value;

        return $this;
    }

    #[ElementQueryParam(ElementQueryParamType::StringOrStrings, 'Narrows the query results based on the menus the nodes belong to, per the menus’ handles.')]
    public function menuHandle(mixed $value): static
    {
        $this->menuHandle = $value;

        return $this;
    }

    #[ElementQueryParam(ElementQueryParamType::StringOrStrings, 'Narrows the query results based on the node type (entry, asset, product, custom, passive, site).')]
    public function nodeType(mixed $value): static
    {
        if (is_array($value)) {
            $value = array_map(fn ($v) => $v instanceof NodeType ? $v->value : $v, $value);
        }

        $this->nodeType = $value instanceof NodeType ? $value->value : $value;

        return $this;
    }

    #[ElementQueryParam(ElementQueryParamType::NullableBoolean, 'Narrows the query results to nodes that have a URL (anything but passive nodes), or that don’t.')]
    public function hasUrl(?bool $value = true): static
    {
        $this->hasUrl = $value;

        return $this;
    }

    #[ElementQueryParam(ElementQueryParamType::IntOrInts, 'Narrows the query results based on the ID of the element the nodes link to.')]
    public function linkedElementId(mixed $value): static
    {
        $this->linkedElementId = $value;

        return $this;
    }

    /**
     * Leaves out nodes whose visibility rules hide them from the current visitor. Applied to the
     * results, after the query has run, so it doesn't change `count()` or `limit()`.
     */
    public function visibleOnly(?bool $value = true): static
    {
        $this->visibleOnly = $value;

        return $this;
    }

    #[Override]
    public function afterHydrate(Collection $elements): Collection
    {
        if ($this->visibleOnly) {
            $elements = $elements->filter(fn (Node $node) => $node->isVisible())->values();
        }

        return parent::afterHydrate($elements);
    }

    /**
     * Rendered menus are cached against the nodes of their menu, as Craft tags them.
     *
     * @return string[]
     */
    #[Override]
    protected function cacheTags(): array
    {
        $tags = [];

        if ($this->menuHandle !== null) {
            foreach ((array) $this->menuHandle as $handle) {
                if (is_string($handle)) {
                    $tags[] = "menu:$handle";
                }
            }
        }

        return $tags;
    }
}
