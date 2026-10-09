<?php

namespace justinholt\freenav\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use justinholt\freenav\elements\Node;
use justinholt\freenav\FreeNav;
use yii\console\ExitCode;

class MenusController extends Controller
{
    public $defaultAction = 'index';

    /**
     * @var string|null The JSON file to write (export) or read (import). Export prints to stdout without one.
     */
    public ?string $file = null;

    /**
     * @var bool Replace the nodes of an existing menu with the file's nodes.
     */
    public bool $replace = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'export') {
            $options[] = 'file';
        } elseif ($actionID === 'import') {
            $options[] = 'file';
            $options[] = 'replace';
        }

        return $options;
    }

    public function actionIndex(): int
    {
        $menus = FreeNav::getInstance()->getMenus()->getAllMenus();

        if (empty($menus)) {
            $this->stdout("No menus found.\n");
            return ExitCode::OK;
        }

        $this->stdout("FreeNav Menus:\n");
        $this->stdout(str_repeat('-', 60) . "\n");

        foreach ($menus as $menu) {
            $nodeCount = FreeNav::getInstance()->getNodes()->findNodesInMenu($menu->id)->count();
            $this->stdout(sprintf(
                "  %-30s %-20s %d nodes\n",
                $menu->name,
                $menu->handle,
                $nodeCount,
            ));
        }

        return ExitCode::OK;
    }

    public function actionResaveNodes(?string $handle = null): int
    {
        if ($handle) {
            $menu = FreeNav::getInstance()->getMenus()->getMenuByHandle($handle);
            if (!$menu) {
                $this->stderr("Menu not found: {$handle}\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $nodes = FreeNav::getInstance()->getNodes()->findNodesInMenu($menu->id)->all();
        } else {
            $nodes = Node::find()->siteId('*')->unique()->status(null)->all();
        }

        $count = count($nodes);
        $this->stdout("Resaving {$count} nodes...\n");

        $success = 0;
        foreach ($nodes as $node) {
            if (Craft::$app->getElements()->saveElement($node, false)) {
                $success++;
            } else {
                $this->stderr("Failed to resave node #{$node->id}: {$node->title}\n");
            }
        }

        $this->stdout("Done. {$success}/{$count} nodes resaved.\n");

        return ExitCode::OK;
    }

    public function actionClearCache(?string $handle = null): int
    {
        if ($handle) {
            FreeNav::getInstance()->getMenuCache()->invalidate($handle);
            $this->stdout("Cache cleared for menu: {$handle}\n");
        } else {
            FreeNav::getInstance()->getMenuCache()->invalidateAll();
            $this->stdout("All FreeNav caches cleared.\n");
        }

        return ExitCode::OK;
    }

    /**
     * Exports a menu's nodes as JSON — the same file the control panel's "Export JSON" downloads.
     */
    public function actionExport(string $handle): int
    {
        $menu = FreeNav::getInstance()->getMenus()->getMenuByHandle($handle);

        if (!$menu) {
            $this->stderr("Menu not found: {$handle}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $transfer = FreeNav::getInstance()->getMenuTransfer();
        $export = $transfer->exportMenu($menu);
        $json = $transfer->encode($export);

        if ($this->file === null || $this->file === '-') {
            $this->stdout($json);
            return ExitCode::OK;
        }

        FileHelper::writeToFile($this->file, $json);
        $this->stdout("Exported {$handle} to {$this->file}.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Imports a menu from a JSON export.
     *
     * Creates the menu when no menu has the handle (needs allowAdminChanges). When one does,
     * pass --replace to replace its nodes; its settings are kept. Pass a handle to import into
     * a different menu than the one the file was exported from.
     */
    public function actionImport(?string $handle = null): int
    {
        if (!$this->file) {
            $this->stderr("Pass the export file with --file=<path> (or --file=- for stdin).\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        $path = $this->file === '-' ? 'php://stdin' : $this->file;

        if ($this->file !== '-' && !is_file($path)) {
            $this->stderr("File not found: {$this->file}\n", Console::FG_RED);
            return ExitCode::NOINPUT;
        }

        $transfer = FreeNav::getInstance()->getMenuTransfer();
        $data = $transfer->decode((string)file_get_contents($path));

        if (!$data) {
            $this->stderr("{$this->file} is not a FreeNav menu export.\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $result = $transfer->importMenu($data, $handle, $this->replace);

        foreach ($result->warnings as $warning) {
            $this->stdout("Warning: {$warning}\n", Console::FG_YELLOW);
        }

        if (!$result->getSuccess()) {
            foreach ($result->errors as $error) {
                $this->stderr("{$error}\n", Console::FG_RED);
            }
            $this->stderr("Nothing was imported.\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $menu = $result->menu;
        $this->stdout(sprintf(
            "%s %s: %d node(s) imported%s.\n",
            $result->createdMenu ? 'Created' : 'Updated',
            $menu?->handle,
            $result->nodesImported,
            $result->nodesRemoved ? ", {$result->nodesRemoved} replaced" : '',
        ), Console::FG_GREEN);

        return ExitCode::OK;
    }
}
