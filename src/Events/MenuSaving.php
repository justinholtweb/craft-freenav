<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Events;

use JustinHoltWeb\FreeNav\Models\Menu;

/**
 * Fired before a menu is saved (FreeNav 5: `Menus::EVENT_BEFORE_SAVE_MENU`).
 */
class MenuSaving
{
    public function __construct(
        public Menu $menu,
        public bool $isNew = false,
    ) {}
}
