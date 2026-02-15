<?php

namespace justinholt\freenav\services;

use Craft;
use yii\base\Component;
use yii\caching\TagDependency;

class MenuCache extends Component
{
    private const TAG_PREFIX = 'freenav';

    public function get(string $handle, string $siteId, string $key): ?string
    {
        $cacheKey = $this->_buildKey($handle, $siteId, $key);
        $value = Craft::$app->getCache()->get($cacheKey);

        return $value !== false ? $value : null;
    }

    public function set(string $handle, string $siteId, string $key, string $html, int $duration = 3600): void
    {
        $cacheKey = $this->_buildKey($handle, $siteId, $key);

        $dependency = new TagDependency([
            'tags' => [
                self::TAG_PREFIX,
                self::TAG_PREFIX . ':menu:' . $handle,
            ],
        ]);

        Craft::$app->getCache()->set($cacheKey, $html, $duration, $dependency);
    }

    public function invalidate(string $handle): void
    {
        TagDependency::invalidate(
            Craft::$app->getCache(),
            self::TAG_PREFIX . ':menu:' . $handle
        );
    }

    public function invalidateAll(): void
    {
        TagDependency::invalidate(
            Craft::$app->getCache(),
            self::TAG_PREFIX
        );
    }

    private function _buildKey(string $handle, string $siteId, string $key): string
    {
        return self::TAG_PREFIX . ':' . $handle . ':' . $siteId . ':' . $key;
    }
}
