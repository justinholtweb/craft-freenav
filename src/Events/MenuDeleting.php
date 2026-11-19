<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Events;

use JustinHoltWeb\FreeNav\Models\Menu;

/**
 * Fired before a menu is deleted (FreeNav 5: `Menus::EVENT_BEFORE_DELETE_MENU`).
 */
class MenuDeleting
{
    public function __construct(
        public Menu $menu,
    ) {}
}
