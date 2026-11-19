<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Gql\Resolvers;

use BadMethodCallException;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Gql\Resolvers\ElementResolver;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Elements\Queries\NodeQuery;
use JustinHoltWeb\FreeNav\Helpers\GqlHelper;
use JustinHoltWeb\FreeNav\Services\Menus;

class NodeResolver extends ElementResolver
{
    /**
     * Nodes are scoped to the menus the schema may read, whatever the arguments ask for: an
     * argument naming another menu narrows the result to nothing.
     */
    public static function prepareQuery(mixed $source, array $arguments, ?string $fieldName = null): mixed
    {
        $allowedUids = GqlHelper::getAllowedMenuUids();
        $allowedMenuIds = [];

        foreach (app(Menus::class)->getAllMenus() as $menu) {
            if (in_array($menu->uid, $allowedUids, true)) {
                $allowedMenuIds[] = (int) $menu->id;
            }
        }

        if ($allowedMenuIds === []) {
            return ElementCollection::empty();
        }

        $query = $source === null ? Node::find() : $source->$fieldName;

        if (! $query instanceof ElementQueryInterface) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            try {
                $query->$key($value);
            } catch (BadMethodCallException $e) {
                if ($value !== null) {
                    throw $e;
                }
            }
        }

        if ($query instanceof NodeQuery) {
            $query->whereIn('freenav_nodes.menuId', $allowedMenuIds);

            if ($query->menuId === null && $query->menuHandle === null && count($allowedMenuIds) === 1) {
                $query->menuId($allowedMenuIds[0]);
            }
        }

        return $query;
    }
}
