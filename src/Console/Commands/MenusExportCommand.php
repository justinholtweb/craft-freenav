<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\File;
use Illuminate\Console\Command;
use JustinHoltWeb\FreeNav\Services\Menus;
use JustinHoltWeb\FreeNav\Services\MenuTransfer;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exports a menu's nodes as JSON — the same file the control panel's "Export JSON" downloads.
 */
class MenusExportCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'free-nav:menus:export
        {handle : The menu to export}
        {--file= : The file to write; prints to stdout without one (or with -)}';

    #[Override]
    protected $description = 'Exports a FreeNav menu as JSON.';

    #[Override]
    protected $aliases = ['free-nav/menus/export'];

    public function handle(Menus $menus, MenuTransfer $transfer): int
    {
        $handle = (string) $this->argument('handle');
        $menu = $menus->getMenuByHandle($handle);

        if (! $menu) {
            $this->error("Menu not found: {$handle}");

            return self::FAILURE;
        }

        $json = $transfer->encode($transfer->exportMenu($menu));
        $file = $this->option('file');

        if (! is_string($file) || $file === '' || $file === '-') {
            $this->output->write($json, false, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        File::writeToFile($file, $json);
        $this->info("Exported {$handle} to {$file}.");

        return self::SUCCESS;
    }
}
