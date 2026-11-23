<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Services;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Section\Enums\SectionType;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JustinHoltWeb\FreeNav\Enums\NodeType;
use Traversable;

use function CraftCms\Cms\t;

/**
 * Turns a Structure section that was used as a menu into FreeNav export data, which
 * `MenuTransfer::importMenu()` then validates and writes like any other import.
 *
 * By default each top-level entry becomes a menu and its descendants its nodes. Each entry's link
 * comes from a link field, read by shape rather than class so Craft's Link field, relation fields
 * and third-party link fields all work:
 *
 * - a value with an element (`getElement()`, a relation field's query, an element) → an entry or
 *   asset node, or a custom node with the element's URL for any other element type;
 * - otherwise a URL (`getUrl()`, or a string) → a custom node;
 * - otherwise → a passive node.
 *
 * Without a link field, an entry with a URL links to itself, and one without is passive.
 */
class SectionImporter
{
    /**
     * @return list<array<string, mixed>> one export array per menu, in section order
     *
     * @throws InvalidArgumentException when the section, site or field can't be used
     */
    public function menusFromSection(string $sectionHandle, ?string $linkField = null, ?string $menuHandle = null, ?string $siteHandle = null): array
    {
        $section = Sections::getSectionByHandle($sectionHandle)
            ?? throw new InvalidArgumentException(t('There is no section with the handle “{handle}”.', ['handle' => $sectionHandle], category: 'free-nav'));

        if ($section->type !== SectionType::Structure) {
            throw new InvalidArgumentException(t('“{handle}” isn’t a Structure section.', ['handle' => $sectionHandle], category: 'free-nav'));
        }

        $site = $siteHandle !== null
            ? (Sites::getSiteByHandle($siteHandle, true) ?? throw new InvalidArgumentException(t('There is no site with the handle “{handle}”.', ['handle' => $siteHandle], category: 'free-nav')))
            : Sites::getPrimarySite();

        /** @var Entry[] $entries */
        $entries = Entry::find()
            ->section($sectionHandle)
            ->siteId($site->id)
            ->status(null)
            ->all();

        if ($linkField !== null && ! $this->sectionHasField($entries, $linkField)) {
            throw new InvalidArgumentException(t('The entries in “{section}” have no field with the handle “{field}”.', ['section' => $sectionHandle, 'field' => $linkField], category: 'free-nav'));
        }

        $items = [];

        foreach ($entries as $entry) {
            $items[] = ['level' => (int) ($entry->level ?? 1), 'node' => $this->nodeFromEntry($entry, $linkField), 'entry' => $entry];
        }

        if ($menuHandle !== null) {
            return [$this->menuData($menuHandle, (string) $section->name, self::tree($items))];
        }

        $menus = [];

        foreach (self::tree($items) as $top) {
            $handle = Str::camel((string) ($top['slug'] ?? ''));

            $menus[] = $this->menuData($handle !== '' ? $handle : 'menu'.count($menus), (string) $top['title'], $top['children'] ?? []);
        }

        return $menus;
    }

    /**
     * Nests a flat, structure-ordered list by level: each item's children are the run of deeper
     * items straight after it.
     *
     * @param  list<array{level: int, node: array<string, mixed>}>  $items
     * @return list<array<string, mixed>>
     */
    public static function tree(array $items, int &$i = 0, int $level = 1): array
    {
        $nodes = [];

        while ($i < count($items)) {
            $itemLevel = $items[$i]['level'];

            if ($itemLevel < $level) {
                break;
            }

            $node = $items[$i]['node'];
            $i++;
            $children = self::tree($items, $i, $itemLevel + 1);

            if ($children !== []) {
                $node['children'] = $children;
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * The link part of a node (`nodeType`, and `linkedElementUid`/`customUrl`, `newWindow`,
     * `urlSuffix`) from a link field's value.
     *
     * @return array<string, mixed>
     */
    public static function linkFromValue(mixed $value): array
    {
        $element = self::elementFrom($value);
        $link = [];

        if (is_object($value)) {
            $target = method_exists($value, 'getTarget') ? $value->getTarget() : ($value->target ?? null);

            if ($target === '_blank') {
                $link['newWindow'] = true;
            }
        }

        if ($element !== null) {
            $type = self::nodeTypeFor($element);

            if ($type !== null) {
                $link += ['nodeType' => $type->value, 'linkedElementUid' => $element->uid, 'linkedElementType' => $element::class];

                // Craft's Link field keeps an anchor or query string apart from the element.
                $suffix = is_object($value) && isset($value->urlSuffix) && is_string($value->urlSuffix) ? $value->urlSuffix : null;

                if ($suffix !== null && preg_match('~^[?#&/]~', $suffix)) {
                    $link['urlSuffix'] = $suffix;
                }

                return $link;
            }
        }

        $url = match (true) {
            is_string($value) => trim($value),
            is_object($value) && method_exists($value, 'getUrl') => self::stringOrNull($value->getUrl()),
            default => null,
        };

        if ($url === null || $url === '') {
            $url = $element?->getUrl();
        }

        if ($url === null || $url === '') {
            return ['nodeType' => NodeType::Passive->value] + array_diff_key($link, ['newWindow' => true]);
        }

        return $link + ['nodeType' => NodeType::Custom->value, 'customUrl' => $url];
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeFromEntry(Entry $entry, ?string $linkField): array
    {
        if ($linkField === null) {
            $link = $entry->uri !== null && $entry->uri !== ''
                ? ['nodeType' => NodeType::Entry->value, 'linkedElementUid' => $entry->uid, 'linkedElementType' => $entry::class]
                : ['nodeType' => NodeType::Passive->value];
        } else {
            $link = self::linkFromValue($entry->getFieldValue($linkField));
        }

        return [
            'title' => (string) $entry->title,
            'slug' => (string) $entry->slug,
            'enabled' => (bool) $entry->enabled && (bool) $entry->enabledForSite,
            ...$link,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    private function menuData(string $handle, string $name, array $nodes): array
    {
        return [
            'freeNav' => MenuTransfer::FORMAT_VERSION,
            'menu' => ['name' => $name, 'handle' => $handle],
            'nodes' => $nodes,
        ];
    }

    /**
     * @param  Entry[]  $entries
     */
    private function sectionHasField(array $entries, string $handle): bool
    {
        foreach ($entries as $entry) {
            if ($entry->getFieldLayout()?->getFieldByHandle($handle) !== null) {
                return true;
            }
        }

        return $entries === [];
    }

    private static function elementFrom(mixed $value): ?ElementInterface
    {
        if ($value instanceof ElementInterface) {
            return $value;
        }

        if ($value instanceof ElementQueryInterface) {
            $element = $value->one();

            return $element instanceof ElementInterface ? $element : null;
        }

        if (is_iterable($value)) {
            foreach ($value instanceof Traversable ? $value : (array) $value as $item) {
                return $item instanceof ElementInterface ? $item : null;
            }

            return null;
        }

        if (is_object($value) && method_exists($value, 'getElement')) {
            $element = $value->getElement();

            return $element instanceof ElementInterface ? $element : null;
        }

        return null;
    }

    private static function nodeTypeFor(ElementInterface $element): ?NodeType
    {
        foreach (NodeType::cases() as $type) {
            if ($type->isElement() && $type->isAvailable() && $type->elementType() === $element::class) {
                return $type;
            }
        }

        return null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : null;
    }
}
