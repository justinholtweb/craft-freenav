<?php

namespace justinholt\freenav\services;

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\helpers\StringHelper;
use craft\models\Section;
use craft\models\Site;
use justinholt\freenav\enums\NodeType;
use justinholt\freenav\FreeNav;
use justinholt\freenav\models\ImportResult;
use yii\base\Component;
use yii\base\InvalidArgumentException;

/**
 * Turns a Structure section that was used as a menu into FreeNav menus.
 *
 * Each top-level entry becomes a menu and its descendants its nodes, or (with a menu handle)
 * the whole section becomes one menu. An entry's link comes from its link field: a linked entry
 * or asset becomes an element node, any other link a custom URL, and no link a passive node.
 * Without a link field, each entry links to itself.
 *
 * The menus are built in the export file format and written by `MenuTransfer::importMenu()`,
 * so they are validated, limited and written the same way as an imported file.
 */
class SectionImporter extends Component
{
    /**
     * @return array<int, array{name: string, handle: string, data: array}> one export-format menu per entry
     * @throws InvalidArgumentException when the section or field can't be used
     */
    public function buildMenus(string $sectionHandle, ?string $linkField = null, ?string $menuHandle = null, ?Site $site = null): array
    {
        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);

        if (!$section) {
            throw new InvalidArgumentException(Craft::t('free-nav', 'There is no section with the handle “{handle}”.', ['handle' => $sectionHandle]));
        }

        if ($section->type !== Section::TYPE_STRUCTURE) {
            throw new InvalidArgumentException(Craft::t('free-nav', '“{section}” isn’t a Structure section.', ['section' => $section->name]));
        }

        if ($linkField !== null && !$this->_sectionHasField($section, $linkField)) {
            throw new InvalidArgumentException(Craft::t('free-nav', 'No entry type in “{section}” has a field with the handle “{handle}”.', [
                'section' => $section->name,
                'handle' => $linkField,
            ]));
        }

        $site ??= Craft::$app->getSites()->getPrimarySite();

        $entries = Entry::find()
            ->sectionId($section->id)
            ->siteId($site->id)
            ->status(null)
            ->all();

        $items = array_map(fn(Entry $entry) => [
            'level' => (int)$entry->level,
            'node' => $this->nodeForEntry($entry, $linkField),
        ], $entries);

        if ($menuHandle !== null) {
            return [$this->_menu($section->name, $menuHandle, self::tree($items))];
        }

        $menus = [];
        $current = null;
        $children = [];

        foreach ($items as $i => $item) {
            if ($item['level'] === 1) {
                if ($current !== null) {
                    $menus[] = $this->_menu($current->title, self::handleFor($current), self::tree($children));
                }

                $current = $entries[$i];
                $children = [];
            } else {
                $children[] = ['level' => $item['level'] - 1, 'node' => $item['node']];
            }
        }

        if ($current !== null) {
            $menus[] = $this->_menu($current->title, self::handleFor($current), self::tree($children));
        }

        return $menus;
    }

    /**
     * Imports each built menu. One that fails leaves the others alone.
     *
     * @return array<string, ImportResult> by menu handle
     */
    public function import(array $menus, bool $replace = false): array
    {
        $results = [];

        foreach ($menus as $menu) {
            $results[$menu['handle']] = FreeNav::getInstance()->getMenuTransfer()->importMenu($menu['data'], replace: $replace);
        }

        return $results;
    }

    /**
     * The export-format node for an entry, without children.
     */
    public function nodeForEntry(Entry $entry, ?string $linkField): array
    {
        $node = [
            'title' => (string)$entry->title,
            'enabled' => $entry->enabled && $entry->getEnabledForSite(),
        ];

        if ($linkField === null) {
            return $node + ($entry->uri !== null
                ? ['nodeType' => NodeType::Entry->value, 'linkedElementUid' => $entry->uid]
                : ['nodeType' => NodeType::Passive->value]);
        }

        $value = $entry->getFieldLayout()?->getFieldByHandle($linkField) ? $entry->getFieldValue($linkField) : null;

        return $node + self::linkFromValue($value);
    }

    /**
     * A link field's value as node settings. Duck-typed, so Craft's Link field, relation fields
     * (Entries, Assets…) and link plugins that offer `getElement()` / `getUrl()` all work.
     *
     * @return array{nodeType: string, linkedElementUid?: string, customUrl?: string, newWindow?: bool, urlSuffix?: string}
     */
    public static function linkFromValue(mixed $value): array
    {
        // Craft's LinkData has a public `target`; link plugins tend to offer getTarget().
        $target = is_object($value) ? (method_exists($value, 'getTarget') ? $value->getTarget() : ($value->target ?? null)) : null;
        $extra = $target === '_blank' ? ['newWindow' => true] : [];

        // Craft's Link field keeps an anchor or query string apart from a linked element.
        $suffix = is_object($value) && isset($value->urlSuffix) && is_string($value->urlSuffix) ? $value->urlSuffix : null;
        $elementExtra = $suffix !== null && preg_match('~^[?#&/]~', $suffix) ? $extra + ['urlSuffix' => $suffix] : $extra;

        if ($value instanceof ElementQueryInterface) {
            $value = $value->one();
        } elseif (is_iterable($value)) {
            $first = null;
            foreach ($value as $item) {
                $first = $item;
                break;
            }
            $value = $first;
        }

        $element = match (true) {
            $value instanceof ElementInterface => $value,
            is_object($value) && method_exists($value, 'getElement') => $value->getElement(),
            default => null,
        };

        if ($element instanceof ElementInterface) {
            foreach (NodeType::cases() as $type) {
                $class = $type->elementType();

                if ($type->isElement() && $class && $element instanceof $class) {
                    return ['nodeType' => $type->value, 'linkedElementUid' => $element->uid] + $elementExtra;
                }
            }

            $url = $element->getUrl();

            return $url ? ['nodeType' => NodeType::Custom->value, 'customUrl' => $url] + $extra : ['nodeType' => NodeType::Passive->value];
        }

        $url = match (true) {
            is_object($value) && method_exists($value, 'getUrl') => $value->getUrl(),
            is_string($value) => $value,
            default => null,
        };

        $url = is_string($url) ? trim($url) : '';

        return $url !== ''
            ? ['nodeType' => NodeType::Custom->value, 'customUrl' => $url] + $extra
            : ['nodeType' => NodeType::Passive->value];
    }

    /**
     * Nests `[level, node]` items (in structure order) through each node's `children`.
     */
    public static function tree(array $items): array
    {
        $roots = [];
        $stack = []; // references to the children arrays of the open ancestors, by level

        foreach ($items as $item) {
            $level = max(1, (int)$item['level']);
            $node = $item['node'] + ['children' => []];

            // A level that skips ahead (a missing parent) hangs off the nearest open ancestor.
            $level = min($level, count($stack) + 1);
            array_splice($stack, $level - 1);

            if ($level === 1) {
                $roots[] = $node;
                $stack[] = &$roots[array_key_last($roots)]['children'];
            } else {
                $siblings = &$stack[$level - 2];
                $siblings[] = $node;
                $stack[] = &$siblings[array_key_last($siblings)]['children'];
                unset($siblings);
            }
        }

        return self::_dropEmptyChildren($roots);
    }

    public static function handleFor(Entry $entry): string
    {
        $handle = StringHelper::toCamelCase((string)($entry->slug ?: $entry->title));

        return preg_match('/^[a-zA-Z]/', $handle) ? $handle : 'menu' . ucfirst($handle);
    }

    private static function _dropEmptyChildren(array $nodes): array
    {
        foreach ($nodes as &$node) {
            if ($node['children'] === []) {
                unset($node['children']);
            } else {
                $node['children'] = self::_dropEmptyChildren($node['children']);
            }
        }

        return $nodes;
    }

    private function _menu(string $name, string $handle, array $nodes): array
    {
        return [
            'name' => $name,
            'handle' => $handle,
            'data' => [
                'freeNav' => MenuTransfer::FORMAT_VERSION,
                'menu' => ['name' => $name, 'handle' => $handle],
                'nodes' => $nodes,
            ],
        ];
    }

    private function _sectionHasField(Section $section, string $handle): bool
    {
        foreach ($section->getEntryTypes() as $entryType) {
            if ($entryType->getFieldLayout()->getFieldByHandle($handle)) {
                return true;
            }
        }

        return false;
    }
}
