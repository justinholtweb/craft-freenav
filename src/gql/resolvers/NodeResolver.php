<?php

namespace justinholt\freenav\gql\resolvers;

use craft\gql\base\ElementResolver;
use justinholt\freenav\elements\db\NodeQuery;
use justinholt\freenav\elements\Node;
use justinholt\freenav\FreeNav;
use justinholt\freenav\helpers\GqlHelper;

class NodeResolver extends ElementResolver
{
    public static function prepareQuery(mixed $source, array $arguments, ?string $fieldName = null): mixed
    {
        if (!GqlHelper::canQueryFreeNavNodes()) {
            return [];
        }

        if ($source === null) {
            $query = Node::find();
        } else {
            $query = $source->getChildren();
        }

        if (!$query instanceof NodeQuery) {
            $query = Node::find();
        }

        foreach ($arguments as $key => $value) {
            if (method_exists($query, $key)) {
                $query->$key($value);
            } elseif (property_exists($query, $key)) {
                $query->$key = $value;
            }
        }

        // Scope to only menus the current GQL schema is allowed to read
        $allowedUids = GqlHelper::getAllowedMenuUids();
        $menus = FreeNav::getInstance()->getMenus()->getAllMenus();

        $allowedMenuIds = [];
        foreach ($menus as $menu) {
            if (in_array($menu->uid, $allowedUids, true)) {
                $allowedMenuIds[] = $menu->id;
            }
        }

        if (!empty($allowedMenuIds)) {
            $query->menuId($allowedMenuIds);
        } else {
            return [];
        }

        return $query;
    }
}
