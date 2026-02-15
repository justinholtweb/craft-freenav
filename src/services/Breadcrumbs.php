<?php

namespace justinholt\freenav\services;

use Craft;
use craft\elements\Entry;
use craft\elements\Category;
use yii\base\Component;

class Breadcrumbs extends Component
{
    public function generate(array $options = []): array
    {
        $options = array_merge([
            'homeLabel' => 'Home',
            'homeUrl' => '/',
            'includeHome' => true,
            'includeCurrent' => true,
        ], $options);

        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return [];
        }

        $uri = trim($request->getPathInfo(), '/');
        $segments = $uri ? explode('/', $uri) : [];
        $breadcrumbs = [];

        // Home
        if ($options['includeHome']) {
            $breadcrumbs[] = [
                'title' => $options['homeLabel'],
                'url' => $options['homeUrl'],
                'segment' => '',
                'element' => null,
                'isHome' => true,
                'isCurrent' => empty($segments),
            ];
        }

        // Build breadcrumbs from URL segments
        $builtPath = '';

        foreach ($segments as $index => $segment) {
            $builtPath .= ($builtPath ? '/' : '') . $segment;
            $isLast = ($index === count($segments) - 1);

            if ($isLast && !$options['includeCurrent']) {
                continue;
            }

            // Try to resolve segment to an element
            $element = $this->_resolveSegmentToElement($builtPath);

            $breadcrumbs[] = [
                'title' => $element ? $element->title : $this->_segmentToTitle($segment),
                'url' => '/' . $builtPath,
                'segment' => $segment,
                'element' => $element,
                'isHome' => false,
                'isCurrent' => $isLast,
            ];
        }

        return $breadcrumbs;
    }

    private function _resolveSegmentToElement(string $uri): ?\craft\base\Element
    {
        // Try to find an entry with this URI
        $entry = Entry::find()
            ->uri($uri)
            ->status('enabled')
            ->one();

        if ($entry) {
            return $entry;
        }

        // Try categories
        $category = Category::find()
            ->uri($uri)
            ->status('enabled')
            ->one();

        if ($category) {
            return $category;
        }

        return null;
    }

    private function _segmentToTitle(string $segment): string
    {
        // Convert slug to title case: "about-us" -> "About Us"
        return ucwords(str_replace(['-', '_'], ' ', $segment));
    }
}
