<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\FreeNav\Services\Menus;
use JustinHoltWeb\FreeNav\Services\Nodes;
use Override;

/**
 * Lists every menu with its node count.
 */
class MenusIndexCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'free-nav:menus:index';

    #[Override]
    protected $description = 'Lists every FreeNav menu with its node count.';

    /** `free-nav/menus` was the Craft 5 controller's default action. */
    #[Override]
    protected $aliases = ['free-nav/menus/index', 'free-nav/menus'];

    public function handle(Menus $menus, Nodes $nodes): int
    {
        $all = $menus->getAllMenus();

        if ($all === []) {
            $this->line('No menus found.');

            return self::SUCCESS;
        }

        $this->line('FreeNav Menus:');
        $this->line(str_repeat('-', 60));

        foreach ($all as $menu) {
            $this->line(sprintf(
                '  %-30s %-20s %d nodes',
                $menu->name,
                $menu->handle,
                $nodes->findNodesInMenu((int) $menu->id)->count(),
            ));
        }

        return self::SUCCESS;
    }
}
