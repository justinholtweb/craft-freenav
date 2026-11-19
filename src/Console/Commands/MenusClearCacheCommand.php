<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\FreeNav\Services\MenuCache;
use Override;

/**
 * Clears rendered menu HTML.
 */
class MenusClearCacheCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'free-nav:menus:clear-cache {handle? : The menu to clear; every menu when omitted}';

    #[Override]
    protected $description = 'Clears the cached HTML of a FreeNav menu, or of every menu.';

    #[Override]
    protected $aliases = ['free-nav/menus/clear-cache'];

    public function handle(MenuCache $cache): int
    {
        $handle = $this->argument('handle');

        if (is_string($handle) && $handle !== '') {
            $cache->invalidate($handle);
            $this->info("Cache cleared for menu: {$handle}");
        } else {
            $cache->invalidateAll();
            $this->info('All FreeNav caches cleared.');
        }

        return self::SUCCESS;
    }
}
