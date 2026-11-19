<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Services;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Entry\Elements\Entry;

class Breadcrumbs
{
    /**
     * Breadcrumbs for the current page, or for `uri` when the options name one (the REST API's
     * `get-breadcrumbs?uri=…`).
     *
     * @param  array<string, mixed>  $options
     * @return list<array{title: string, url: string, segment: string, element: ElementInterface|null, isHome: bool, isCurrent: bool}>
     */
    public function generate(array $options = []): array
    {
        $options = array_merge([
            'homeLabel' => 'Home',
            'homeUrl' => '/',
            'includeHome' => true,
            'includeCurrent' => true,
            'uri' => null,
        ], $options);

        if (is_string($options['uri'])) {
            $uri = trim($options['uri'], '/');
        } elseif (app()->runningInConsole()) {
            return [];
        } else {
            $uri = trim(request()->craftPath(), '/');
        }

        $segments = $uri !== '' ? explode('/', $uri) : [];
        $breadcrumbs = [];

        if ($options['includeHome']) {
            $breadcrumbs[] = [
                'title' => (string) $options['homeLabel'],
                'url' => (string) $options['homeUrl'],
                'segment' => '',
                'element' => null,
                'isHome' => true,
                'isCurrent' => $segments === [],
            ];
        }

        $builtPath = '';

        foreach ($segments as $index => $segment) {
            $builtPath .= ($builtPath !== '' ? '/' : '').$segment;
            $isLast = $index === count($segments) - 1;

            if ($isLast && ! $options['includeCurrent']) {
                continue;
            }

            $element = $this->resolveSegmentToElement($builtPath);

            $breadcrumbs[] = [
                'title' => $element ? (string) $element->title : self::segmentToTitle($segment),
                'url' => '/'.$builtPath,
                'segment' => $segment,
                'element' => $element,
                'isHome' => false,
                'isCurrent' => $isLast,
            ];
        }

        return $breadcrumbs;
    }

    /**
     * The live entry at a URI. Craft 6 has no categories, so entries are all there is to find —
     * categories converted to entries are found like any other entry.
     */
    private function resolveSegmentToElement(string $uri): ?ElementInterface
    {
        return Entry::find()->uri($uri)->status('enabled')->one();
    }

    /**
     * Converts a slug to title case: "about-us" -> "About Us".
     */
    public static function segmentToTitle(string $segment): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $segment));
    }
}
