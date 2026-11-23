<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JustinHoltWeb\FreeNav\Services\MenuTransfer;
use JustinHoltWeb\FreeNav\Services\SectionImporter;
use Override;

/**
 * Builds menus from a Structure section that was used as one.
 */
class MigrateFromSectionCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'free-nav:migrate:from-section
        {--section= : The Structure section’s handle}
        {--link-field= : The field holding each entry’s link (a Link field, a relation field, or a URL). Without it, entries link to themselves.}
        {--menu= : Put the whole section into one menu with this handle, instead of one menu per top-level entry}
        {--site= : The site to read the entries from (default: the primary site)}
        {--replace : Replace the nodes of a menu that already exists}';

    #[Override]
    protected $description = 'Creates FreeNav menus from a Structure section: top-level entries become menus, their descendants nodes.';

    #[Override]
    protected $aliases = ['free-nav/migrate/from-section'];

    public function handle(SectionImporter $importer, MenuTransfer $transfer): int
    {
        $section = $this->stringOption('section');

        if ($section === null) {
            $this->error('Pass the section’s handle with --section.');

            return self::FAILURE;
        }

        try {
            $menus = $importer->menusFromSection($section, $this->stringOption('link-field'), $this->stringOption('menu'), $this->stringOption('site'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($menus === []) {
            $this->line("The section '{$section}' has no entries to import.");

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($menus as $data) {
            $handle = $data['menu']['handle'];
            $this->line("Importing: {$data['menu']['name']} ({$handle})...");
            $result = $transfer->importMenu($data, replace: (bool) $this->option('replace'));

            foreach ($result->warnings as $warning) {
                $this->warn('  '.$warning);
            }

            if (! $result->getSuccess()) {
                foreach ($result->errors as $error) {
                    $this->error('  '.$error);
                }

                $failed++;
            } else {
                $this->line(sprintf(
                    '  %s menu “%s”: %d node(s) imported%s.',
                    $result->createdMenu ? 'Created' : 'Updated',
                    $result->menu->handle ?? $handle,
                    $result->nodesImported,
                    $result->nodesRemoved ? ", {$result->nodesRemoved} removed" : '',
                ));
            }

            $this->newLine();
        }

        if ($failed) {
            $this->error("{$failed} of ".count($menus).' menu(s) were not imported.');

            return self::FAILURE;
        }

        $this->info(count($menus).' menu(s) imported.');

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
