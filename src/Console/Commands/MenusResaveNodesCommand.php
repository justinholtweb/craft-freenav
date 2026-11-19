<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\Facades\Elements;
use Illuminate\Console\Command;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Services\Menus;
use JustinHoltWeb\FreeNav\Services\Nodes;
use Override;

/**
 * Re-saves a menu's nodes, or every node.
 */
class MenusResaveNodesCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'free-nav:menus:resave-nodes {handle? : The menu to resave; every menu when omitted}';

    #[Override]
    protected $description = 'Re-saves the nodes of a FreeNav menu, or of every menu.';

    #[Override]
    protected $aliases = ['free-nav/menus/resave-nodes'];

    public function handle(Menus $menus, Nodes $nodes): int
    {
        $handle = $this->argument('handle');

        if (is_string($handle) && $handle !== '') {
            $menu = $menus->getMenuByHandle($handle);

            if (! $menu) {
                $this->error("Menu not found: {$handle}");

                return self::FAILURE;
            }

            $all = $nodes->findNodesInMenu((int) $menu->id)->all();
        } else {
            $all = Node::find()->siteId('*')->unique()->status(null)->all();
        }

        $count = count($all);
        $this->line("Resaving {$count} nodes...");
        $success = 0;

        foreach ($all as $node) {
            if (Elements::saveElement($node, false)) {
                $success++;
            } else {
                $this->error("Failed to resave node #{$node->id}: {$node->title}");
            }
        }

        $this->info("Done. {$success}/{$count} nodes resaved.");

        return $success === $count ? self::SUCCESS : self::FAILURE;
    }
}
