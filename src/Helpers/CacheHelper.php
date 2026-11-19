<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Helpers;

use JustinHoltWeb\FreeNav\Services\MenuCache;

class CacheHelper
{
    public static function invalidateMenuCache(string $handle): void
    {
        app(MenuCache::class)->invalidate($handle);
    }

    public static function invalidateAllCaches(): void
    {
        app(MenuCache::class)->invalidateAll();
    }

    /**
     * @param  array<mixed>  $options
     */
    public static function getCacheKey(array $options): string
    {
        return md5((string) json_encode($options));
    }
}
