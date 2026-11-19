<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustinHoltWeb\FreeNav\Http\Controllers\ApiController;

// The FreeNav 5 REST API, at the same URLs: `actions/free-nav/api/get-menus`, `…/get-menu?handle=`
// and `…/get-breadcrumbs?uri=`. Public on the site action prefix; ApiController applies the
// "Enable REST API" setting and each node's visibility rules.
Route::get('api/get-menus', [ApiController::class, 'getMenus'])->name('free-nav.api.menus');
Route::get('api/get-menu', [ApiController::class, 'getMenu'])->name('free-nav.api.menu');
Route::get('api/get-breadcrumbs', [ApiController::class, 'getBreadcrumbs'])->name('free-nav.api.breadcrumbs');
