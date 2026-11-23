<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nodes can show one of Craft's icons. FreeNav 5.3 adds the same column, so a site upgrading from
 * it already has one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('freenav_nodes') && ! Schema::hasColumn('freenav_nodes', 'craftIcon')) {
            Schema::table('freenav_nodes', function (Blueprint $table) {
                $table->string('craftIcon', 255)->nullable()->after('icon');
            });
        }
    }

    public function down(): void {}
};
