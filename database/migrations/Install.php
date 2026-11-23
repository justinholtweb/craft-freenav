<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Database\Migrations;

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JustinHoltWeb\FreeNav\Elements\Node;

/**
 * FreeNav's tables. A site upgrading from FreeNav 5 already has them, with this shape, so each
 * one is only created when it's missing.
 */
class Install extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('freenav_menus')) {
            Schema::create('freenav_menus', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('structureId')->nullable();
                $table->integer('fieldLayoutId')->nullable();
                $table->string('name', 255);
                $table->string('handle', 255);
                $table->text('instructions')->nullable();
                $table->string('propagationMethod', 20)->default('all');
                $table->integer('maxNodes')->nullable();
                $table->smallInteger('maxLevels')->nullable();
                $table->string('defaultPlacement', 10)->default('end');
                $table->text('permissions')->nullable();
                $table->smallInteger('sortOrder')->default(0);
                $table->dateTime('dateDeleted')->nullable();
                $table->dateTime('dateCreated');
                $table->dateTime('dateUpdated');
                $table->char('uid', 36)->default('0');

                $table->unique(['handle']);
                $table->index(['structureId']);
                $table->index(['fieldLayoutId']);
                $table->index(['dateDeleted']);

                $table->foreign('structureId')->references('id')->on(Table::STRUCTURES)->cascadeOnDelete();
                $table->foreign('fieldLayoutId')->references('id')->on(Table::FIELDLAYOUTS)->nullOnDelete();
            });
        }

        if (! Schema::hasTable('freenav_menu_sites')) {
            Schema::create('freenav_menu_sites', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('menuId');
                $table->integer('siteId');
                $table->boolean('enabled')->default(true);
                $table->dateTime('dateCreated');
                $table->dateTime('dateUpdated');
                $table->char('uid', 36)->default('0');

                $table->unique(['menuId', 'siteId']);

                $table->foreign('menuId')->references('id')->on('freenav_menus')->cascadeOnDelete();
                $table->foreign('siteId')->references('id')->on(Table::SITES)->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('freenav_nodes')) {
            Schema::create('freenav_nodes', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->integer('menuId');
                $table->integer('linkedElementId')->nullable();
                $table->string('nodeType', 50);
                $table->text('customUrl')->nullable();
                $table->string('classes', 255)->nullable();
                $table->string('urlSuffix', 255)->nullable();
                $table->text('customAttributes')->nullable();
                $table->text('data')->nullable();
                $table->boolean('newWindow')->default(false);
                $table->string('icon', 255)->nullable();
                $table->string('craftIcon', 255)->nullable();
                $table->string('badge', 100)->nullable();
                $table->text('visibilityRules')->nullable();
                $table->boolean('deletedWithMenu')->nullable();
                $table->dateTime('dateCreated');
                $table->dateTime('dateUpdated');
                $table->char('uid', 36)->default('0');

                $table->index(['menuId']);
                $table->index(['nodeType']);
                $table->index(['linkedElementId']);

                $table->foreign('id')->references('id')->on(Table::ELEMENTS)->cascadeOnDelete();
                $table->foreign('menuId')->references('id')->on('freenav_menus')->cascadeOnDelete();
                $table->foreign('linkedElementId')->references('id')->on(Table::ELEMENTS)->nullOnDelete();
            });
        }
    }

    /**
     * Uninstalling removes the nodes as elements too — Craft only drops the plugin's tables — along
     * with their search index rows, the menus' structures and their field layouts.
     */
    public function down(): void
    {
        $nodeIds = DB::table(Table::ELEMENTS)->where('type', Node::class)->pluck('id')->all();

        foreach (array_chunk($nodeIds, 500) as $ids) {
            foreach ([Table::SEARCHINDEX, Table::SEARCHINDEXQUEUE] as $searchTable) {
                if (Schema::hasTable($searchTable)) {
                    DB::table($searchTable)->whereIn('elementId', $ids)->delete();
                }
            }

            DB::table(Table::ELEMENTS)->whereIn('id', $ids)->delete();
        }

        if (Schema::hasTable('freenav_menus')) {
            $menus = DB::table('freenav_menus')->get(['structureId', 'fieldLayoutId']);
            $structureIds = $menus->pluck('structureId')->filter()->all();
            $layoutIds = $menus->pluck('fieldLayoutId')->filter()->all();

            Schema::dropIfExists('freenav_nodes');
            Schema::dropIfExists('freenav_menu_sites');
            Schema::dropIfExists('freenav_menus');

            if ($structureIds !== []) {
                DB::table(Table::STRUCTURES)->whereIn('id', $structureIds)->delete();
            }

            if ($layoutIds !== []) {
                DB::table(Table::FIELDLAYOUTS)->whereIn('id', $layoutIds)->delete();
            }
        }

        Schema::dropIfExists('freenav_nodes');
        Schema::dropIfExists('freenav_menu_sites');
        Schema::dropIfExists('freenav_menus');
    }
}
