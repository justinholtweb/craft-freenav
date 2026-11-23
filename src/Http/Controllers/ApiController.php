<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\FreeNav;
use JustinHoltWeb\FreeNav\Services\Breadcrumbs;
use JustinHoltWeb\FreeNav\Services\Menus;

/**
 * The REST API: `actions/free-nav/api/get-menus`, `get-menu?handle=…` and
 * `get-breadcrumbs?uri=…`.
 *
 * Anonymous, and on unless turned off in the settings — so it applies the same visibility rules
 * the rendered menu does, and only lists enabled nodes.
 */
class ApiController
{
    public function __construct(
        private readonly Menus $menus,
        private readonly Breadcrumbs $breadcrumbs,
    ) {}

    public function getMenus(): JsonResponse
    {
        $this->requireEnabled();

        $data = [];

        foreach ($this->menus->getAllMenus() as $menu) {
            $data[] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'handle' => $menu->handle,
                'maxLevels' => $menu->maxLevels,
                'maxNodes' => $menu->maxNodes,
            ];
        }

        return response()->json(['menus' => $data]);
    }

    public function getMenu(Request $request): JsonResponse
    {
        $this->requireEnabled();

        $handle = $request->query('handle');
        abort_unless(is_string($handle) && $handle !== '', 400, 'Missing required parameter: handle');

        $menu = $this->menus->getMenuByHandle($handle) ?? abort(404, 'Menu not found');

        // Top level only — each node carries its own children.
        $nodes = Node::find()
            ->menuHandle($handle)
            ->level(1)
            ->status('enabled')
            ->all();

        $nodeData = [];

        foreach ($nodes as $node) {
            // The same visibility rules the rendered menu applies: a "members only" link is not
            // for an anonymous API caller either.
            if ($node->isVisible()) {
                $nodeData[] = $this->serializeNode($node);
            }
        }

        return response()->json([
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'handle' => $menu->handle,
                'maxLevels' => $menu->maxLevels,
            ],
            'nodes' => $nodeData,
        ]);
    }

    public function getBreadcrumbs(Request $request): JsonResponse
    {
        $this->requireEnabled();

        $uri = $request->query('uri');
        $breadcrumbs = $this->breadcrumbs->generate(['uri' => is_string($uri) ? $uri : '']);

        // The element itself would serialise every field it has; callers get what a breadcrumb
        // needs.
        foreach ($breadcrumbs as &$crumb) {
            $element = $crumb['element'];
            $crumb['element'] = $element ? ['id' => $element->id, 'title' => $element->title, 'url' => $element->getUrl()] : null;
        }

        return response()->json(['breadcrumbs' => $breadcrumbs]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNode(Node $node): array
    {
        $children = array_filter(
            Node::find()->descendantOf($node)->descendantDist(1)->siteId($node->siteId)->status('enabled')->all(),
            fn (Node $child) => $child->isVisible(),
        );

        return [
            'id' => $node->id,
            'title' => $node->title,
            'url' => $node->getUrl(),
            'nodeType' => $node->nodeType,
            'level' => $node->level,
            'classes' => $node->classes,
            'urlSuffix' => $node->urlSuffix,
            'customAttributes' => Node::safeCustomAttributes($node->getCustomAttributesArray()) ?? [],
            'newWindow' => $node->newWindow,
            'icon' => $node->icon,
            'craftIcon' => $node->craftIcon,
            'badge' => $node->badge,
            'active' => $node->isActive(),
            'enabled' => $node->enabled,
            'children' => array_values(array_map(fn (Node $child) => $this->serializeNode($child), $children)),
        ];
    }

    private function requireEnabled(): void
    {
        abort_unless(FreeNav::getInstance()->getSettings()->restApiEnabled, 404, 'REST API is disabled.');
    }
}
