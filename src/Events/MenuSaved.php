<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Events;

use JustinHoltWeb\FreeNav\Models\Menu;

/**
 * Fired after a menu has been saved and its project-config change applied (FreeNav 5: `Menus::EVENT_AFTER_SAVE_MENU`).
 */
class MenuSaved
{
    public function __construct(
        public Menu $menu,
        public bool $isNew = false,
    ) {}
}
