<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Services;

use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Data\UserGroup;
use CraftCms\Cms\View\TemplateMode;
use Illuminate\Support\HtmlString;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Enums\Preset;
use JustinHoltWeb\FreeNav\FreeNav;
use JustinHoltWeb\FreeNav\Helpers\NodeHelper;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\template;

class Renderer
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function render(string $handle, array $options = []): HtmlString
    {
        $settings = FreeNav::getInstance()->getSettings();

        $options = array_merge([
            'preset' => $settings->defaultPreset ?: Preset::Default->value,
            'id' => null,
            'class' => null,
            'ulClass' => null,
            'liClass' => null,
            'aClass' => null,
            'activeClass' => $settings->activeClass,
            'hasChildrenClass' => $settings->hasChildrenClass,
            'maxLevel' => null,
            'overrideTemplate' => null,
            'cache' => $settings->cacheEnabled,
            'cacheDuration' => $settings->cacheDuration,
            'aria' => $settings->ariaEnabled,
            'visibilityCheck' => true,
        ], $options);

        // A query string would make every cache key unique — `?x=1`, `?x=2`… is an easy way to
        // fill the cache — and those pages are rarely the ones worth caching, so render them fresh.
        if (! app()->runningInConsole() && request()->query() !== []) {
            $options['cache'] = false;
        }

        $cache = app(MenuCache::class);
        $siteId = (string) Sites::getCurrentSite()->id;
        $cacheKey = $options['cache'] ? $this->cacheKey($options) : null;

        if ($cacheKey !== null) {
            $cached = $cache->get($handle, $siteId, $cacheKey);

            if ($cached !== null) {
                return new HtmlString($cached);
            }
        }

        $preset = Preset::tryFrom((string) ($options['preset'] ?? Preset::Default->value)) ?? Preset::Default;
        $html = $this->renderMenu($handle, $preset, $options);

        if ($cacheKey !== null) {
            $cache->set($handle, $siteId, $cacheKey, $html, (int) $options['cacheDuration']);
        }

        return new HtmlString($html);
    }

    /**
     * What the rendered HTML depends on, beyond the options: the page (active and current
     * classes, URL-segment rules) and who is looking (logged-in and user-group rules).
     *
     * Until 5.1.5 the key was the options alone, so the first visitor's render was served to
     * everyone — a menu rendered for a logged-in admin, members-only links included, went to
     * anonymous visitors, and the active item was whatever page warmed the cache.
     *
     * @param  array<string, mixed>  $options
     */
    private function cacheKey(array $options): string
    {
        $user = app()->runningInConsole() ? null : currentUserElement();
        $groups = $user ? array_map(fn (UserGroup $group) => $group->id, $user->getGroups()) : [];
        sort($groups);

        $context = [
            'options' => $options,
            'url' => app()->runningInConsole() ? '' : request()->url(),
            'user' => $user ? ['in' => true, 'groups' => $groups] : ['in' => false],
        ];

        return md5((string) json_encode($context));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function renderPreset(string $handle, Preset $preset, array $options = []): HtmlString
    {
        $options['preset'] = $preset->value;

        return $this->render($handle, $options);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return list<array{node: Node, children: array<mixed>}>
     */
    public function tree(string $handle, array $criteria = []): array
    {
        $query = Node::find()->menuHandle($handle)->status('enabled');

        foreach ($criteria as $key => $value) {
            $query->$key = $value;
        }

        return NodeHelper::buildTree($query->all());
    }

    public function getActiveNode(string $handle): ?Node
    {
        foreach (Node::find()->menuHandle($handle)->status('enabled')->all() as $node) {
            if ($node->isCurrent()) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function renderMenu(string $handle, Preset $preset, array $options): string
    {
        $query = Node::find()->menuHandle($handle)->status('enabled');

        if ($options['maxLevel'] ?? null) {
            $query->level('<= '.(int) $options['maxLevel']);
        }

        $nodes = $query->all();

        if ($options['visibilityCheck']) {
            $nodes = array_filter($nodes, fn (Node $node) => $node->isVisible());

            // Presets nest by level, so drop the descendants of any node that was filtered out
            // instead of letting them surface as their own top-level items
            $nodes = NodeHelper::flattenTree(NodeHelper::buildTree(array_values($nodes)));
        }

        if ($nodes === []) {
            return '';
        }

        $menu = app(Menus::class)->getMenuByHandle($handle);
        $template = ! empty($options['overrideTemplate']) ? (string) $options['overrideTemplate'] : $preset->templatePath();

        return template($template, [
            'nodes' => $nodes,
            'menu' => $menu,
            'options' => $options,
        ], TemplateMode::Site);
    }
}
