<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\FreeNav\Services\MenuTransfer;
use Override;

/**
 * Imports a menu from a JSON export.
 *
 * Creates the menu when no menu has the handle (needs allowAdminChanges). When one does, pass
 * --replace to replace its nodes; its settings are kept. Pass a handle to import into a different
 * menu than the one the file was exported from.
 */
class MenusImportCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'free-nav:menus:import
        {handle? : The menu to import into; the file’s own handle when omitted}
        {--file= : The export file to read, or - for stdin}
        {--replace : Replace the nodes of an existing menu with the file’s nodes}';

    #[Override]
    protected $description = 'Imports a FreeNav menu from a JSON export.';

    #[Override]
    protected $aliases = ['free-nav/menus/import'];

    /** sysexits.h, as the Craft 5 command returned */
    private const int EX_DATAERR = 65;

    private const int EX_NOINPUT = 66;

    public function handle(MenuTransfer $transfer): int
    {
        $file = $this->option('file');

        if (! is_string($file) || $file === '') {
            $this->error('Pass the export file with --file=<path> (or --file=- for stdin).');

            return self::INVALID;
        }

        $path = $file === '-' ? 'php://stdin' : $file;

        if ($file !== '-' && ! is_file($path)) {
            $this->error("File not found: {$file}");

            return self::EX_NOINPUT;
        }

        $data = $transfer->decode((string) file_get_contents($path));

        if (! $data) {
            $this->error("{$file} is not a FreeNav menu export.");

            return self::EX_DATAERR;
        }

        $handle = $this->argument('handle');
        $result = $transfer->importMenu($data, is_string($handle) && $handle !== '' ? $handle : null, (bool) $this->option('replace'));

        foreach ($result->warnings as $warning) {
            $this->warn("Warning: {$warning}");
        }

        if (! $result->getSuccess()) {
            foreach ($result->errors as $error) {
                $this->error($error);
            }

            $this->error('Nothing was imported.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s %s: %d node(s) imported%s.',
            $result->createdMenu ? 'Created' : 'Updated',
            $result->menu?->handle,
            $result->nodesImported,
            $result->nodesRemoved ? ", {$result->nodesRemoved} replaced" : '',
        ));

        return self::SUCCESS;
    }
}
