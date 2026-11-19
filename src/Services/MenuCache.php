<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Services;

use CraftCms\DependencyAwareCache\Dependency\TagDependency;
use CraftCms\DependencyAwareCache\Facades\DependencyCache;
use JustinHoltWeb\FreeNav\Elements\Node;

/**
 * Rendered menu HTML.
 *
 * Each entry depends on FreeNav's own tags (`freenav`, `freenav:menu:{handle}`, cleared when a
 * menu definition changes or from the console) and on the tags Craft clears for nodes: the menu's
 * node tag, cleared when one of its nodes is saved or deleted, and the node type's, cleared when a
 * node moves in a structure.
 */
class MenuCache
{
    private const string TAG_PREFIX = 'freenav';

    public function get(string $handle, string $siteId, string $key): ?string
    {
        $value = DependencyCache::get($this->buildKey($handle, $siteId, $key));

        return is_string($value) ? $value : null;
    }

    public function set(string $handle, string $siteId, string $key, string $html, int $duration = 3600): void
    {
        $dependency = new TagDependency([
            self::TAG_PREFIX,
            self::TAG_PREFIX.':menu:'.$handle,
            'element::'.Node::class.'::menu:'.$handle,
            'element::'.Node::class,
        ]);

        DependencyCache::put($this->buildKey($handle, $siteId, $key), $html, $duration > 0 ? $duration : null, $dependency);
    }

    public function invalidate(string $handle): void
    {
        TagDependency::invalidate(self::TAG_PREFIX.':menu:'.$handle);
    }

    public function invalidateAll(): void
    {
        TagDependency::invalidate(self::TAG_PREFIX);
    }

    private function buildKey(string $handle, string $siteId, string $key): string
    {
        return self::TAG_PREFIX.':'.$handle.':'.$siteId.':'.$key;
    }
}
