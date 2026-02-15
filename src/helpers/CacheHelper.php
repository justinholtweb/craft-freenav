<?php

namespace justinholt\freenav\helpers;

use justinholt\freenav\FreeNav;

class CacheHelper
{
    public static function invalidateMenuCache(string $handle): void
    {
        FreeNav::getInstance()->getMenuCache()->invalidate($handle);
    }

    public static function invalidateAllCaches(): void
    {
        FreeNav::getInstance()->getMenuCache()->invalidateAll();
    }

    public static function getCacheKey(array $options): string
    {
        return md5(json_encode($options));
    }
}
