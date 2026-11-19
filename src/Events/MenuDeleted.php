<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Events;

use JustinHoltWeb\FreeNav\Models\Menu;

/**
 * Fired after a menu and its nodes have been deleted (FreeNav 5: `Menus::EVENT_AFTER_DELETE_MENU`).
 */
class MenuDeleted
{
    public function __construct(
        public Menu $menu,
    ) {}
}
