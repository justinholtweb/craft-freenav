<?php

namespace justinholt\freenav\console\controllers;

use Craft;
use craft\console\Controller;
use justinholt\freenav\elements\Node;
use justinholt\freenav\FreeNav;
use yii\console\ExitCode;

class MenusController extends Controller
{
    public $defaultAction = 'index';

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
            $nodeCount = Node::find()->menuId($menu->id)->count();
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

            $nodes = Node::find()->menuId($menu->id)->status(null)->all();
        } else {
            $nodes = Node::find()->status(null)->all();
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
}
