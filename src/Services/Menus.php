<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Services;

use CraftCms\Cms\Cms;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Structure\Data\Structure;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Structures;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Events\MenuDeleted;
use JustinHoltWeb\FreeNav\Events\MenuDeleting;
use JustinHoltWeb\FreeNav\Events\MenuSaved;
use JustinHoltWeb\FreeNav\Events\MenuSaving;
use JustinHoltWeb\FreeNav\Models\Menu;
use JustinHoltWeb\FreeNav\Models\MenuSiteSettings;

use function CraftCms\Cms\currentUserElement;

/**
 * Menu definitions. A menu is project config (`freeNav.menus.{uid}`); `freenav_menus`,
 * `freenav_menu_sites`, its structure and its field layout are written by the project-config
 * handler, so a deploy that applies project config builds them the same way.
 */
class Menus
{
    public const string CONFIG_PATH = 'freeNav.menus';

    public const string TABLE = 'freenav_menus';

    public const string SITES_TABLE = 'freenav_menu_sites';

    /** @var Menu[]|null */
    private ?array $menus = null;

    /**
     * @return Menu[]
     */
    public function getAllMenus(): array
    {
        if ($this->menus !== null) {
            return $this->menus;
        }

        return $this->menus = DB::table(self::TABLE)
            ->whereNull('dateDeleted')
            ->orderBy('sortOrder')
            ->orderBy('name')
            ->get()
            ->map(fn (object $row) => $this->createMenu($row))
            ->all();
    }

    public function getMenuById(int $id): ?Menu
    {
        return $this->firstWhere('id', $id);
    }

    public function getMenuByHandle(string $handle): ?Menu
    {
        return $this->firstWhere('handle', $handle);
    }

    public function getMenuByUid(string $uid): ?Menu
    {
        return $this->firstWhere('uid', $uid);
    }

    private function firstWhere(string $column, int|string $value): ?Menu
    {
        $row = DB::table(self::TABLE)->where($column, $value)->whereNull('dateDeleted')->first();

        return $row ? $this->createMenu($row) : null;
    }

    private function createMenu(object $row): Menu
    {
        $data = (array) $row;

        foreach (['id', 'structureId', 'fieldLayoutId', 'maxNodes', 'maxLevels'] as $int) {
            $data[$int] = isset($data[$int]) ? (int) $data[$int] : null;
        }

        $data['sortOrder'] = (int) ($data['sortOrder'] ?? 0);

        return new Menu($data);
    }

    /**
     * The menus the current user can open: ones they manage, and ones whose nodes they can edit
     * (an "edit nodes" user still needs to reach the tree).
     *
     * @return Menu[]
     */
    public function getEditableMenus(?User $user = null): array
    {
        $user ??= currentUserElement();

        if (! $user) {
            return [];
        }

        return array_values(array_filter(
            $this->getAllMenus(),
            fn (Menu $menu) => $this->canManageMenu($user, $menu) || $this->canEditNodes($user, $menu),
        ));
    }

    // Permissions — the one place the documented meanings live:
    //   freeNav-manageMenus            create, edit and delete menus
    //   freeNav-manageMenu:{uid}       edit one menu's settings
    //   freeNav-editNodes(:{uid})      add, edit, move and toggle nodes
    //   freeNav-deleteNodes(:{uid})    delete nodes
    // -------------------------------------------------------------------------

    public function canManageMenus(User $user): bool
    {
        return $user->admin || $user->can('freeNav-manageMenus');
    }

    public function canManageMenu(User $user, Menu $menu): bool
    {
        return $this->canManageMenus($user) || $user->can('freeNav-manageMenu:'.$menu->uid);
    }

    public function canEditNodes(User $user, Menu $menu): bool
    {
        return $user->admin || $user->can('freeNav-editNodes') || $user->can('freeNav-editNodes:'.$menu->uid);
    }

    public function canDeleteNodes(User $user, Menu $menu): bool
    {
        return $user->admin || $user->can('freeNav-deleteNodes') || $user->can('freeNav-deleteNodes:'.$menu->uid);
    }

    /**
     * Whether menu definitions can change here. Menus are project config, so like sections they
     * only change where admin changes are allowed — otherwise production drifts from what is
     * deployed.
     */
    public function allowAdminChanges(): bool
    {
        return Cms::config()->allowAdminChanges;
    }

    /**
     * @return array<int, MenuSiteSettings>
     */
    public function getMenuSiteSettings(int $menuId): array
    {
        $settings = [];

        foreach (DB::table(self::SITES_TABLE)->where('menuId', $menuId)->get() as $row) {
            $settings[(int) $row->siteId] = new MenuSiteSettings([
                'id' => (int) $row->id,
                'menuId' => (int) $row->menuId,
                'siteId' => (int) $row->siteId,
                'enabled' => (bool) $row->enabled,
                'uid' => $row->uid,
            ]);
        }

        return $settings;
    }

    public function saveMenu(Menu $menu, bool $runValidation = true): bool
    {
        $isNew = ! $menu->id;

        if (Event::hasListeners(MenuSaving::class)) {
            event(new MenuSaving($menu, $isNew));
        }

        if ($runValidation && ! $menu->validate()) {
            Log::info('FreeNav: menu not saved due to validation errors.');

            return false;
        }

        $menu->uid ??= (string) Str::uuid();

        $config = $menu->getConfig();
        // The structure's UID goes into project config from the start, so every environment that
        // applies it builds the same structure.
        $config['structure']['uid'] ??= (string) Str::uuid();

        ProjectConfig::set(self::CONFIG_PATH.'.'.$menu->uid, $config, "Save FreeNav menu “{$menu->handle}”");

        if ($isNew) {
            $menu->id = $this->getMenuByUid($menu->uid)?->id;
        }

        $this->menus = null;

        return true;
    }

    public function handleChangedMenu(ConfigEvent $event): void
    {
        $uid = (string) $event->tokenMatches[0];
        $data = is_array($event->newValue) ? $event->newValue : [];
        $now = now();
        $isNew = false;

        DB::transaction(function () use ($uid, $data, $now, &$isNew) {
            $maxLevels = isset($data['maxLevels']) && $data['maxLevels'] !== '' ? (int) $data['maxLevels'] : null;

            // Structure
            $structureUid = $data['structure']['uid'] ?? null;
            $structure = $structureUid ? Structures::getStructureByUid($structureUid, true) : null;

            if (! $structure) {
                $structure = new Structure;
                $structure->uid = is_string($structureUid) ? $structureUid : (string) Str::uuid();
            }

            $structure->maxLevels = $maxLevels;
            Structures::saveStructure($structure);

            // Field layout
            $fieldLayoutId = null;

            if (! empty($data['fieldLayout']) && is_array($data['fieldLayout'])) {
                $fieldLayout = FieldLayout::createFromConfig($data['fieldLayout']);
                $fieldLayout->type = Node::class;
                Fields::saveLayout($fieldLayout, false);
                $fieldLayoutId = $fieldLayout->id;
            }

            $row = [
                'structureId' => $structure->id,
                'fieldLayoutId' => $fieldLayoutId,
                'name' => (string) ($data['name'] ?? ''),
                'handle' => (string) ($data['handle'] ?? ''),
                'instructions' => $data['instructions'] ?? null,
                'propagationMethod' => $data['propagationMethod'] ?? 'all',
                'maxNodes' => isset($data['maxNodes']) && $data['maxNodes'] !== '' ? (int) $data['maxNodes'] : null,
                'maxLevels' => $maxLevels,
                'defaultPlacement' => $data['defaultPlacement'] ?? 'end',
                'dateDeleted' => null,
                'dateUpdated' => $now,
            ];

            $menuId = DB::table(self::TABLE)->where('uid', $uid)->value('id');

            if ($menuId) {
                DB::table(self::TABLE)->where('id', $menuId)->update($row);
            } else {
                $isNew = true;
                $menuId = DB::table(self::TABLE)->insertGetId($row + ['dateCreated' => $now, 'uid' => $uid]);
            }

            // Site settings
            DB::table(self::SITES_TABLE)->where('menuId', $menuId)->delete();

            foreach (($data['sites'] ?? []) as $siteUid => $siteData) {
                $site = self::siteByUid((string) $siteUid);

                if ($site) {
                    DB::table(self::SITES_TABLE)->insert([
                        'menuId' => $menuId,
                        'siteId' => $site->id,
                        'enabled' => (bool) ($siteData['enabled'] ?? true),
                        'dateCreated' => $now,
                        'dateUpdated' => $now,
                        'uid' => (string) Str::uuid(),
                    ]);
                }
            }
        });

        $this->menus = null;
        app(MenuCache::class)->invalidateAll();

        $menu = $this->getMenuByUid($uid);

        if ($menu && Event::hasListeners(MenuSaved::class)) {
            event(new MenuSaved($menu, $isNew));
        }
    }

    public function deleteMenu(Menu $menu): bool
    {
        if (Event::hasListeners(MenuDeleting::class)) {
            event(new MenuDeleting($menu));
        }

        ProjectConfig::remove(self::CONFIG_PATH.'.'.$menu->uid, "Delete FreeNav menu “{$menu->handle}”");

        $this->menus = null;

        return true;
    }

    public function handleDeletedMenu(ConfigEvent $event): void
    {
        $uid = (string) $event->tokenMatches[0];
        $row = DB::table(self::TABLE)->where('uid', $uid)->first();

        if (! $row) {
            return;
        }

        $menu = $this->createMenu($row);

        DB::transaction(function () use ($menu) {
            // Every node, the trashed ones included, so nothing is left for garbage collection.
            $nodes = Node::find()
                ->menuId($menu->id)
                ->siteId('*')
                ->unique()
                ->status(null)
                ->trashed(null)
                ->withStructure(false)
                ->all();

            foreach ($nodes as $node) {
                Elements::deleteElement($node, true);
            }

            DB::table(self::SITES_TABLE)->where('menuId', $menu->id)->delete();

            if ($menu->structureId) {
                Structures::deleteStructureById($menu->structureId);
            }

            if ($menu->fieldLayoutId) {
                Fields::deleteLayoutById($menu->fieldLayoutId);
            }

            DB::table(self::TABLE)->where('id', $menu->id)->delete();
        });

        $this->menus = null;
        app(MenuCache::class)->invalidate((string) $menu->handle);

        if (Event::hasListeners(MenuDeleted::class)) {
            event(new MenuDeleted($menu));
        }
    }

    /**
     * @param  array<int|string>  $ids  Menu IDs in their new order
     */
    public function reorderMenus(array $ids): bool
    {
        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $order => $id) {
                DB::table(self::TABLE)->where('id', (int) $id)->update(['sortOrder' => $order]);
            }
        });

        $this->menus = null;

        return true;
    }

    /**
     * Copies a menu, its field layout and its nodes, keeping them nested the same way.
     */
    public function duplicateMenu(Menu $menu): ?Menu
    {
        $newMenu = new Menu;
        $newMenu->name = $menu->name.' Copy';
        $newMenu->handle = $this->freeHandle($menu->handle.'Copy');
        $newMenu->instructions = $menu->instructions;
        $newMenu->propagationMethod = $menu->propagationMethod;
        $newMenu->maxNodes = $menu->maxNodes;
        $newMenu->maxLevels = $menu->maxLevels;
        $newMenu->defaultPlacement = $menu->defaultPlacement;

        $siteSettings = [];

        foreach ($menu->getSiteSettings() as $siteId => $settings) {
            $siteSettings[$siteId] = new MenuSiteSettings(['siteId' => $settings->siteId, 'enabled' => $settings->enabled]);
        }

        $newMenu->setSiteSettings($siteSettings);

        $layoutConfig = $menu->getFieldLayout()->getConfig();

        if ($layoutConfig) {
            $layout = FieldLayout::createFromConfig($layoutConfig);
            $layout->type = Node::class;
            $layout->resetUids();
            $newMenu->setFieldLayout($layout);
        }

        if (! $this->saveMenu($newMenu)) {
            return null;
        }

        $newMenu = $this->getMenuById((int) $newMenu->id) ?? $newMenu;

        // The source nodes come back in structure order, so a level stack is enough to find each
        // one's new parent.
        $nodes = app(Nodes::class)->findNodesInMenu((int) $menu->id)->all();
        $nodesService = app(Nodes::class);
        $stack = []; // [newNode, level]

        foreach ($nodes as $node) {
            $level = $node->level ?? 1;

            while ($stack !== [] && $stack[count($stack) - 1][1] >= $level) {
                array_pop($stack);
            }

            $newNode = new Node;
            $newNode->menuId = $newMenu->id;
            $newNode->siteId = $node->siteId;
            $newNode->title = $node->title;
            $newNode->nodeType = $node->nodeType;
            $newNode->linkedElementId = $node->linkedElementId;
            $newNode->customUrl = $node->customUrl;
            $newNode->classes = $node->classes;
            $newNode->urlSuffix = $node->urlSuffix;
            $newNode->customAttributes = $node->customAttributes;
            $newNode->data = $node->data;
            $newNode->newWindow = $node->newWindow;
            $newNode->icon = $node->icon;
            $newNode->badge = $node->badge;
            $newNode->visibilityRules = $node->visibilityRules;
            $newNode->enabled = $node->enabled;

            if (! Elements::saveElement($newNode, false)) {
                continue;
            }

            $parent = $stack !== [] ? $stack[count($stack) - 1][0] : null;
            $nodesService->placeNode($newMenu, $newNode, $parent);

            $stack[] = [$newNode, $level];
        }

        return $newMenu;
    }

    /**
     * A handle no menu uses yet, starting from `$handle`.
     */
    public function freeHandle(string $handle): string
    {
        $candidate = $handle;
        $i = 2;

        while ($this->getMenuByHandle($candidate)) {
            $candidate = $handle.$i++;
        }

        return $candidate;
    }

    /**
     * Site settings that enable a menu for every site.
     *
     * @return array<int, MenuSiteSettings>
     */
    public function allSitesSettings(): array
    {
        $settings = [];

        foreach (Sites::getAllSites() as $site) {
            $settings[(int) $site->id] = new MenuSiteSettings(['siteId' => (int) $site->id, 'enabled' => true]);
        }

        return $settings;
    }

    private static function siteByUid(string $uid): ?Site
    {
        return Sites::getAllSites(true)->first(fn (Site $site) => $site->uid === $uid);
    }
}
