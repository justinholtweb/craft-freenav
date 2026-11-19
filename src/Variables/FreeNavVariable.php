<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Variables;

use Illuminate\Support\HtmlString;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Elements\Queries\NodeQuery;
use JustinHoltWeb\FreeNav\Models\Menu;
use JustinHoltWeb\FreeNav\Services\Breadcrumbs;
use JustinHoltWeb\FreeNav\Services\Menus;
use JustinHoltWeb\FreeNav\Services\Nodes;
use JustinHoltWeb\FreeNav\Services\Renderer;

/**
 * `craft.freenav` in Twig (and `$craft->freenav` in Blade).
 */
class FreeNavVariable
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function render(string $handle, array $options = []): HtmlString
    {
        return app(Renderer::class)->render($handle, $options);
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function nodes(string $handle, array $criteria = []): NodeQuery
    {
        return app(Nodes::class)->getNodesByMenuHandle($handle, $criteria);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return list<array{node: Node, children: array<mixed>}>
     */
    public function tree(string $handle, array $criteria = []): array
    {
        return app(Renderer::class)->tree($handle, $criteria);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function breadcrumbs(array $options = []): array
    {
        return app(Breadcrumbs::class)->generate($options);
    }

    public function getActiveNode(string $handle): ?Node
    {
        return app(Renderer::class)->getActiveNode($handle);
    }

    public function getMenuByHandle(string $handle): ?Menu
    {
        return app(Menus::class)->getMenuByHandle($handle);
    }

    public function getMenuById(int $id): ?Menu
    {
        return app(Menus::class)->getMenuById($id);
    }

    /**
     * @return Menu[]
     */
    public function getAllMenus(): array
    {
        return app(Menus::class)->getAllMenus();
    }
}
