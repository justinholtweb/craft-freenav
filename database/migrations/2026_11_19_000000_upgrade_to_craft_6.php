<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Helpers\LegacyClassMap;
use JustinHoltWeb\FreeNav\Models\Menu;
use JustinHoltWeb\FreeNav\Services\Menus;

/**
 * Brings a FreeNav 5.2.1 install onto FreeNav 6.
 *
 * 1. FreeNav's classes moved from `justinholt\freenav\…` to `JustinHoltWeb\FreeNav\…`. Craft stores
 *    class names in elements, field layouts, fields and project config, so each is rewritten
 *    (core does the same for its own classes in its type migrations).
 * 2. Craft 6 has no categories. Category nodes whose element Craft's category-to-entry conversion
 *    turned into an entry (it keeps the element ID) become entry nodes; the rest become custom URL
 *    nodes pointing where the category was, or are disabled when it had no URL.
 * 3. FreeNav 5 had no field layout designer, so menus get the default node layout.
 *
 * Every step only changes what still needs changing, so running it again does nothing, and on a
 * fresh install there is nothing to change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->renameClasses();
        $this->convertCategoryNodes();
        $this->addFieldLayouts();
    }

    private function renameClasses(): void
    {
        $renamed = 0;

        foreach ([Table::ELEMENTS => 'type', Table::FIELDLAYOUTS => 'type', Table::FIELDS => 'type'] as $table => $column) {
            foreach (LegacyClassMap::MAP as $old => $new) {
                $renamed += DB::table($table)->where($column, $old)->update([$column => $new]);
            }
        }

        foreach ([Table::FIELDLAYOUTS => 'config', Table::FIELDS => 'settings'] as $table => $column) {
            foreach (DB::table($table)->whereNotNull($column)->where($column, 'like', '%justinholt%')->lazyById() as $row) {
                $updated = LegacyClassMap::replace($row->$column);

                if ($updated !== $row->$column) {
                    DB::table($table)->where('id', $row->id)->update([$column => $updated]);
                    $renamed++;
                }
            }
        }

        $projectConfig = app(ProjectConfig::class);
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        try {
            foreach ((array) $projectConfig->get() as $key => $config) {
                $updated = LegacyClassMap::replace($config);

                if ($updated !== $config) {
                    $projectConfig->set((string) $key, $updated, 'Rename FreeNav classes for Craft 6');
                    $renamed++;
                }
            }

            $projectConfig->saveModifiedConfigData();
        } finally {
            $projectConfig->muteEvents = $muteEvents;
        }

        if ($renamed > 0) {
            Log::info("FreeNav: renamed FreeNav 5 class names in {$renamed} places.");
        }
    }

    private function convertCategoryNodes(): void
    {
        $nodes = DB::table('freenav_nodes')->where('nodeType', 'category')->get();

        foreach ($nodes as $node) {
            $type = $node->linkedElementId
                ? DB::table(Table::ELEMENTS)->where('id', $node->linkedElementId)->value('type')
                : null;

            if (in_array($type, [Entry::class, 'craft\\elements\\Entry'], true)) {
                DB::table('freenav_nodes')->where('id', $node->id)->update(['nodeType' => 'entry']);
                Log::info("FreeNav: node #{$node->id} linked to category #{$node->linkedElementId}, now an entry; it is an entry node now.");

                continue;
            }

            $url = $node->linkedElementId ? $this->lastKnownUrl((int) $node->linkedElementId, (int) $node->id) : null;

            DB::table('freenav_nodes')->where('id', $node->id)->update([
                'nodeType' => 'custom',
                'customUrl' => $url,
                'linkedElementId' => null,
            ]);

            if ($url === null) {
                DB::table(Table::ELEMENTS)->where('id', $node->id)->update(['enabled' => false]);
                Log::warning("FreeNav: node #{$node->id} linked to a category that is no longer an entry and had no URL; it is now a disabled custom URL node.");
            } else {
                Log::warning("FreeNav: node #{$node->id} linked to a category that is no longer an entry; it is now a custom URL node pointing to {$url}.");
            }
        }
    }

    /**
     * Where the category was, on one of the node's sites.
     */
    private function lastKnownUrl(int $elementId, int $nodeId): ?string
    {
        $nodeSiteIds = DB::table(Table::ELEMENTS_SITES)->where('elementId', $nodeId)->pluck('siteId')->all();

        $row = DB::table(Table::ELEMENTS_SITES)
            ->where('elementId', $elementId)
            ->whereNotNull('uri')
            ->orderByRaw($nodeSiteIds !== [] ? 'case when siteId in ('.implode(',', array_map('intval', $nodeSiteIds)).') then 0 else 1 end' : '1')
            ->first(['siteId', 'uri']);

        if (! $row) {
            return null;
        }

        $baseUrl = Sites::getSiteById((int) $row->siteId, true)?->getBaseUrl();

        if (! $baseUrl) {
            return null;
        }

        return $row->uri === '__home__' ? $baseUrl : rtrim($baseUrl, '/').'/'.ltrim((string) $row->uri, '/');
    }

    private function addFieldLayouts(): void
    {
        $projectConfig = app(ProjectConfig::class);

        foreach (DB::table('freenav_menus')->whereNull('dateDeleted')->get() as $row) {
            $path = Menus::CONFIG_PATH.'.'.$row->uid;
            $config = $projectConfig->get($path);

            if (! is_array($config) || ! empty($config['fieldLayout'])) {
                continue;
            }

            $layout = $row->fieldLayoutId ? Fields::getLayoutById((int) $row->fieldLayoutId) : null;
            $layout ??= Menu::defaultFieldLayout();
            $layout->type = Node::class;
            $layout->uid ??= (string) Str::uuid();
            $layoutConfig = $layout->getConfig();

            if (! $layoutConfig || ! Fields::saveLayout($layout, false)) {
                continue;
            }

            DB::table('freenav_menus')->where('id', $row->id)->update(['fieldLayoutId' => $layout->id]);

            $muteEvents = $projectConfig->muteEvents;
            $projectConfig->muteEvents = true;

            try {
                $projectConfig->set("$path.fieldLayout", ['uid' => $layout->uid] + $layoutConfig, 'Add a field layout to a FreeNav menu');
            } finally {
                $projectConfig->muteEvents = $muteEvents;
            }

            Log::info("FreeNav: menu “{$row->handle}” got the default node field layout.");
        }
    }

    public function down(): void {}
};
