<?php

namespace justinholt\freenav\variables;

use justinholt\freenav\elements\db\NodeQuery;
use justinholt\freenav\elements\Node;
use justinholt\freenav\FreeNav;
use justinholt\freenav\models\Menu;
use Twig\Markup;

class FreeNavVariable
{
    public function render(string $handle, array $options = []): Markup
    {
        return FreeNav::getInstance()->getRenderer()->render($handle, $options);
    }

    public function nodes(string $handle, array $criteria = []): NodeQuery
    {
        return FreeNav::getInstance()->getNodes()->getNodesByMenuHandle($handle, $criteria);
    }

    public function tree(string $handle, array $criteria = []): array
    {
        return FreeNav::getInstance()->getRenderer()->tree($handle, $criteria);
    }

    public function breadcrumbs(array $options = []): array
    {
        return FreeNav::getInstance()->getBreadcrumbs()->generate($options);
    }

    public function getActiveNode(string $handle): ?Node
    {
        return FreeNav::getInstance()->getRenderer()->getActiveNode($handle);
    }

    public function getMenuByHandle(string $handle): ?Menu
    {
        return FreeNav::getInstance()->getMenus()->getMenuByHandle($handle);
    }

    public function getMenuById(int $id): ?Menu
    {
        return FreeNav::getInstance()->getMenus()->getMenuById($id);
    }

    public function getAllMenus(): array
    {
        return FreeNav::getInstance()->getMenus()->getAllMenus();
    }
}
