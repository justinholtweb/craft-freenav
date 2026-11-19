<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\FreeNav\Services\VerbbImporter;
use Override;

/**
 * Copies Verbb Navigation's navs and nodes into FreeNav menus.
 */
class MigrateFromNavigationCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'free-nav:migrate:from-navigation';

    #[Override]
    protected $description = 'Migrates every Verbb Navigation nav and its nodes into FreeNav menus.';

    /** `free-nav/migrate` was the Craft 5 controller's default action. */
    #[Override]
    protected $aliases = ['free-nav/migrate/from-navigation', 'free-nav/migrate'];

    public function handle(VerbbImporter $verbb): int
    {
        if (! $verbb->tablesExist()) {
            $this->error('Verbb Navigation tables not found. Is the plugin installed?');

            return self::FAILURE;
        }

        $navs = $verbb->navs();

        if ($navs === []) {
            $this->line('No navigations found to migrate.');

            return self::SUCCESS;
        }

        $this->line('Found '.count($navs).' navigation(s) to migrate.');
        $this->newLine();
        $migrated = 0;

        foreach ($navs as $nav) {
            $this->line("Migrating: {$nav['name']} ({$nav['handle']})...");
            $result = $verbb->migrateNav($nav);

            if ($result === null) {
                $this->line("  Skipped: a menu with the handle '{$nav['handle']}' already exists, or it couldn't be saved.");
            } else {
                $this->line("  Migrated {$result['nodes']} node(s).");
                $migrated++;
            }

            $this->newLine();
        }

        $this->info("Migration complete. {$migrated} navigation(s) migrated.");

        return self::SUCCESS;
    }
}
