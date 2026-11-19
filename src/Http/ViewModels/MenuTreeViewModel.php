<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Http\ViewModels;

use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\ViewModels\ElementTypeIndexViewModel;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Models\Menu;
use Override;

use function CraftCms\Cms\cp_url;

/**
 * Craft's element index for nodes, opened on one menu's source in structure view.
 */
class MenuTreeViewModel extends ElementTypeIndexViewModel
{
    public function __construct(
        ElementIndexRequest $request,
        private readonly Menu $menu,
    ) {
        parent::__construct(Node::class, $request);
    }

    #[Override]
    protected function defaultSourceKey(): ?string
    {
        return 'menu:'.$this->menu->uid;
    }

    /**
     * Each menu's source is addressed by its tree URL, the way sections are by theirs.
     *
     * @param  array<string, mixed>  $source
     */
    #[Override]
    protected function sourceUrl(array $source): ?string
    {
        $menuId = $source['data']['menu-id'] ?? null;

        return is_numeric($menuId) ? cp_url("free-nav/menus/{$menuId}/build") : parent::sourceUrl($source);
    }
}
