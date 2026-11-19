<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustinHoltWeb\FreeNav\Http\Controllers\ImportExportController;
use JustinHoltWeb\FreeNav\Http\Controllers\MenusController;
use JustinHoltWeb\FreeNav\Http\Controllers\NodesController;

use function CraftCms\Cms\cp_url;

// The CP group already applies `web`, `auth`, `can:accessCp` and the cpTrigger prefix. Every route
// also needs access to FreeNav, as every Craft 5 controller required; each action then checks the
// menu permission it needs through `Services\Menus`, and menu-definition writes `allowAdminChanges`.

Route::middleware('can:accessPlugin-free-nav')->group(function () {
    // Route::redirect() would drop the cpTrigger prefix, so redirect through cp_url().
    Route::get('free-nav', fn () => redirect(cp_url('free-nav/menus')))->name('free-nav');
    Route::get('free-nav/settings', fn () => redirect(cp_url('settings/plugins/free-nav')))->name('free-nav.settings');

    Route::get('free-nav/menus', [MenusController::class, 'index'])->name('free-nav.menus');
    Route::get('free-nav/menus/new', [MenusController::class, 'create'])->name('free-nav.menus.create');
    Route::post('free-nav/menus', [MenusController::class, 'save'])->name('free-nav.menus.save');
    Route::post('free-nav/menus/reorder', [MenusController::class, 'reorder'])->name('free-nav.menus.reorder');
    Route::post('free-nav/menus/duplicate', [MenusController::class, 'duplicate'])->name('free-nav.menus.duplicate');
    Route::get('free-nav/menus/{menuId}', [MenusController::class, 'edit'])->whereNumber('menuId')->name('free-nav.menus.edit');
    Route::delete('free-nav/menus/{menuId}', [MenusController::class, 'destroy'])->whereNumber('menuId')->name('free-nav.menus.destroy');

    Route::get('free-nav/menus/{menuId}/build', [NodesController::class, 'tree'])->whereNumber('menuId')->name('free-nav.menus.build');
    Route::get('free-nav/menus/{menuId}/nodes/new', [NodesController::class, 'create'])->whereNumber('menuId')->name('free-nav.nodes.create');
    Route::post('free-nav/menus/{menuId}/nodes', [NodesController::class, 'store'])->whereNumber('menuId')->name('free-nav.nodes.store');
    Route::post('free-nav/menus/{menuId}/nodes/ui', [NodesController::class, 'refresh'])->whereNumber('menuId')->name('free-nav.nodes.refresh');

    Route::get('free-nav/menus/{menuId}/export', [ImportExportController::class, 'export'])->whereNumber('menuId')->name('free-nav.menus.export');
    Route::get('free-nav/import', [ImportExportController::class, 'index'])->name('free-nav.import');
    Route::post('free-nav/import', [ImportExportController::class, 'import'])->name('free-nav.import.json');
    Route::post('free-nav/import/navigation', [ImportExportController::class, 'migrateFromNavigation'])->name('free-nav.import.navigation');
});
