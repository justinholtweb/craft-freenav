<?php

namespace justinholt\freenav\helpers;

use craft\helpers\Gql as CraftGqlHelper;

class GqlHelper
{
    public static function canQueryFreeNavNodes(): bool
    {
        $allowedEntities = CraftGqlHelper::extractAllowedEntitiesFromSchema('read');
        return isset($allowedEntities['freeNavMenus']);
    }

    public static function getAllowedMenuUids(): array
    {
        $allowedEntities = CraftGqlHelper::extractAllowedEntitiesFromSchema('read');
        return $allowedEntities['freeNavMenus'] ?? [];
    }
}
