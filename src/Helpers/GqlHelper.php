<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Helpers;

use CraftCms\Cms\Gql\GqlHelper as CraftGqlHelper;

class GqlHelper
{
    public const string COMPONENT = 'freeNavMenus';

    public static function canQueryFreeNavNodes(): bool
    {
        return isset(CraftGqlHelper::extractAllowedEntitiesFromSchema('read')[self::COMPONENT]);
    }

    /**
     * @return string[]
     */
    public static function getAllowedMenuUids(): array
    {
        return CraftGqlHelper::extractAllowedEntitiesFromSchema('read')[self::COMPONENT] ?? [];
    }
}
