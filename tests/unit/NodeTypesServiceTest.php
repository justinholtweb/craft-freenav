<?php

declare(strict_types=1);

namespace justinholt\freenav\tests\unit;

use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use justinholt\freenav\enums\NodeType;
use justinholt\freenav\services\NodeTypes;
use PHPUnit\Framework\TestCase;

/**
 * Guards the linkable-element contract that the node builder's element-select
 * endpoint (NodesController::actionElementSelectHtml) relies on: each element
 * node type must map to a resolvable element class keyed by its enum value.
 */
final class NodeTypesServiceTest extends TestCase
{
    public function testGetTypeOptionsFlagsElementTypes(): void
    {
        $options = (new NodeTypes())->getTypeOptions();
        $byValue = [];
        foreach ($options as $option) {
            $byValue[$option['value']] = $option;
        }

        self::assertTrue($byValue['entry']['isElement']);
        self::assertFalse($byValue['custom']['isElement']);
        self::assertFalse($byValue['passive']['isElement']);
    }

    public function testLinkableElementTypesAreKeyedByNodeTypeValue(): void
    {
        $linkable = (new NodeTypes())->getLinkableElementTypes();

        // The builder posts a node type value (e.g. "entry") and the endpoint
        // looks it up here, so the keys must be the enum string values.
        self::assertArrayHasKey('entry', $linkable);
        self::assertArrayHasKey('category', $linkable);
        self::assertArrayHasKey('asset', $linkable);

        self::assertArrayNotHasKey('custom', $linkable);
        self::assertArrayNotHasKey('passive', $linkable);
        self::assertArrayNotHasKey('site', $linkable);
    }

    public function testLinkableElementTypesResolveToRealElementClasses(): void
    {
        $linkable = (new NodeTypes())->getLinkableElementTypes();

        self::assertSame(Entry::class, $linkable['entry']['elementType']);
        self::assertSame(Category::class, $linkable['category']['elementType']);
        self::assertSame(Asset::class, $linkable['asset']['elementType']);

        foreach ($linkable as $value => $info) {
            self::assertInstanceOf(NodeType::class, $info['nodeType']);
            self::assertSame($value, $info['nodeType']->value);
            self::assertTrue(
                class_exists($info['elementType']),
                "Element class for “{$value}” is not autoloadable",
            );
        }
    }
}
