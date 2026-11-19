<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Services;

use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\User\Contracts\CraftUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Enums\NodeType;
use JustinHoltWeb\FreeNav\Models\Menu;
use JustinHoltWeb\FreeNav\Models\MenuSiteSettings;

/**
 * Copies Verbb Navigation's navs and nodes into FreeNav menus, reading Navigation's tables
 * directly. The control panel and `free-nav:migrate:from-navigation` share it.
 *
 * Navigation doesn't need to be installed, only its tables present: a site upgraded to Craft 6
 * keeps them. Navs whose handle a menu already uses are skipped.
 */
class VerbbImporter
{
    /**
     * Verbb node types, by the class Navigation stores, mapped to FreeNav node types. Categories
     * map to entries: Craft's category-to-entry conversion keeps element IDs.
     */
    private const array TYPE_MAP = [
        'verbb\\navigation\\nodetypes\\CustomType' => 'custom',
        'verbb\\navigation\\nodetypes\\PassiveType' => 'passive',
        'verbb\\navigation\\nodetypes\\SiteType' => 'site',
        'craft\\elements\\Entry' => 'entry',
        'craft\\elements\\Category' => 'entry',
        'craft\\elements\\Asset' => 'asset',
        'craft\\commerce\\elements\\Product' => 'product',
    ];

    public function __construct(
        private readonly Menus $menus,
        private readonly Nodes $nodes,
    ) {}

    public function tablesExist(): bool
    {
        return Schema::hasTable('navigation_navs') && Schema::hasTable('navigation_nodes');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function navs(): array
    {
        return DB::table('navigation_navs')
            ->whereNull('dateDeleted')
            ->get()
            ->map(fn (object $row) => (array) $row)
            ->all();
    }

    /**
     * Copies one nav. Returns the new menu and how many nodes came across, or null when the
     * handle is taken or the menu couldn't be saved.
     *
     * @param  array<string, mixed>  $nav
     * @return array{menu: Menu, nodes: int}|null
     */
    public function migrateNav(array $nav, ?CraftUser $user = null): ?array
    {
        if ($this->menus->getMenuByHandle((string) $nav['handle'])) {
            return null;
        }

        $menu = new Menu;
        $menu->name = (string) $nav['name'];
        $menu->handle = (string) $nav['handle'];
        $menu->instructions = $nav['instructions'] ?? null;
        $menu->propagationMethod = $nav['propagationMethod'] ?? 'all';
        $menu->maxNodes = isset($nav['maxNodes']) ? (int) $nav['maxNodes'] : null;
        $menu->maxLevels = null;
        $menu->defaultPlacement = $nav['defaultPlacement'] ?? 'end';

        $siteSettings = [];

        if (Schema::hasTable('navigation_navs_sites')) {
            foreach (DB::table('navigation_navs_sites')->where('navId', $nav['id'])->get() as $navSite) {
                $siteSettings[(int) $navSite->siteId] = new MenuSiteSettings([
                    'siteId' => (int) $navSite->siteId,
                    'enabled' => (bool) $navSite->enabled,
                ]);
            }
        }

        $menu->setSiteSettings($siteSettings ?: $this->menus->allSitesSettings());

        if (! $this->menus->saveMenu($menu)) {
            return null;
        }

        $menu = $this->menus->getMenuById((int) $menu->id) ?? $menu;

        return ['menu' => $menu, 'nodes' => $this->migrateNodes($nav, $menu, $user)];
    }

    /**
     * @param  array<string, mixed>  $nav
     */
    private function migrateNodes(array $nav, Menu $menu, ?CraftUser $user): int
    {
        $rows = DB::table('navigation_nodes as n')
            ->select([
                'n.id', 'n.elementId', 'n.url', 'n.type', 'n.classes', 'n.urlSuffix',
                'n.customAttributes', 'n.data', 'n.newWindow', 'es.title', 'se.level',
            ])
            ->join(Table::ELEMENTS.' as e', 'e.id', '=', 'n.id')
            ->join(Table::ELEMENTS_SITES.' as es', 'es.elementId', '=', 'n.id')
            ->leftJoin(Table::STRUCTUREELEMENTS.' as se', function ($join) use ($nav) {
                $join->on('se.elementId', '=', 'n.id')->where('se.structureId', '=', $nav['structureId']);
            })
            ->where('n.navId', $nav['id'])
            ->whereNull('e.dateDeleted')
            ->orderBy('se.lft')
            ->get();

        $stack = []; // [newNodeId, level]
        $count = 0;

        foreach ($rows as $row) {
            $level = (int) ($row->level ?? 1);

            while ($stack !== [] && $stack[count($stack) - 1][1] >= $level) {
                array_pop($stack);
            }

            $attributes = is_string($row->customAttributes) ? Json::decodeIfJson($row->customAttributes) : $row->customAttributes;

            $nodeType = self::mapType($row->type);
            $type = NodeType::from($nodeType);
            $elementId = $this->existingElementId($row->elementId ?? null);

            // Only an element of the node type's class, and in the control panel one the user
            // may view (the console migration has no user).
            if ($elementId !== null && ! $this->nodes->linkableElement($type, $elementId, $this->nodes->getDefaultSiteId($menu), $user)) {
                $elementId = null;
            }

            $created = $this->nodes->addNodes($menu, [[
                'title' => (string) ($row->title ?? ''),
                'nodeType' => $nodeType,
                'customUrl' => $row->url ?? null,
                'linkedElementId' => $elementId,
                'classes' => $row->classes ?? null,
                'urlSuffix' => $row->urlSuffix ?? null,
                'customAttributes' => Node::safeCustomAttributes(is_array($attributes) ? $attributes : null),
                'data' => $row->data ?? null,
                'newWindow' => (bool) ($row->newWindow ?? false),
                'enabled' => true,
                'parentId' => $stack !== [] ? $stack[count($stack) - 1][0] : null,
            ]]);

            if ($created !== []) {
                $stack[] = [$created[0]->id, $level];
                $count++;
            }
        }

        return $count;
    }

    public static function mapType(?string $type): string
    {
        if ($type === null) {
            return 'custom';
        }

        return self::TYPE_MAP[str_replace('\\\\', '\\', $type)] ?? 'custom';
    }

    private function existingElementId(mixed $elementId): ?int
    {
        if (! $elementId) {
            return null;
        }

        return DB::table(Table::ELEMENTS)->where('id', $elementId)->whereNull('dateDeleted')->exists()
            ? (int) $elementId
            : null;
    }
}
