<?php

declare(strict_types=1);

namespace justinholt\freenav\tests\unit;

use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use justinholt\freenav\enums\NodeType;
use PHPUnit\Framework\TestCase;

/**
 * Pure-logic tests for the NodeType backed enum, the plugin's central
 * "enums over FQCN strings" design decision. No Craft app bootstrap required.
 */
final class NodeTypeTest extends TestCase
{
    public function testBackingValues(): void
    {
        self::assertSame('entry', NodeType::Entry->value);
        self::assertSame('category', NodeType::Category->value);
        self::assertSame('asset', NodeType::Asset->value);
        self::assertSame('product', NodeType::Product->value);
        self::assertSame('custom', NodeType::Custom->value);
        self::assertSame('passive', NodeType::Passive->value);
        self::assertSame('site', NodeType::Site->value);
    }

    public function testFromStringResolvesEnum(): void
    {
        self::assertSame(NodeType::Custom, NodeType::from('custom'));
        self::assertNull(NodeType::tryFrom('nonexistent'));
    }

    public function testEveryCaseHasNonEmptyLabel(): void
    {
        foreach (NodeType::cases() as $case) {
            self::assertNotSame('', $case->label(), "{$case->name} has empty label");
        }
    }

    public function testEveryCaseHasValidHexColor(): void
    {
        foreach (NodeType::cases() as $case) {
            self::assertMatchesRegularExpression(
                '/^#[0-9a-fA-F]{6}$/',
                $case->color(),
                "{$case->name} does not return a valid 6-digit hex color",
            );
        }
    }

    public function testHasUrlOnlyFalseForPassive(): void
    {
        self::assertFalse(NodeType::Passive->hasUrl());

        foreach (NodeType::cases() as $case) {
            if ($case !== NodeType::Passive) {
                self::assertTrue($case->hasUrl(), "{$case->name} should have a URL");
            }
        }
    }

    /**
     * isElement() must be true exactly when elementType() returns a class.
     * Product is the exception: it only maps to a class when Commerce is
     * installed, so isElement() is true but elementType() may be null.
     */
    public function testIsElementAgreesWithElementType(): void
    {
        foreach (NodeType::cases() as $case) {
            if (!$case->isElement()) {
                self::assertNull(
                    $case->elementType(),
                    "{$case->name} is not an element but returns an element type",
                );
            }
        }

        self::assertTrue(NodeType::Entry->isElement());
        self::assertTrue(NodeType::Category->isElement());
        self::assertTrue(NodeType::Asset->isElement());
        self::assertTrue(NodeType::Product->isElement());

        self::assertFalse(NodeType::Custom->isElement());
        self::assertFalse(NodeType::Passive->isElement());
        self::assertFalse(NodeType::Site->isElement());
    }

    public function testElementTypeMapsToCraftClasses(): void
    {
        self::assertSame(Entry::class, NodeType::Entry->elementType());
        self::assertSame(Category::class, NodeType::Category->elementType());
        self::assertSame(Asset::class, NodeType::Asset->elementType());
    }

    public function testProductElementTypeIsNullWithoutCommerce(): void
    {
        // Commerce is not a dependency of the test suite.
        if (class_exists('craft\\commerce\\elements\\Product')) {
            self::markTestSkipped('Craft Commerce is installed.');
        }

        self::assertNull(NodeType::Product->elementType());
    }

    public function testElementTypesAreAutoloadable(): void
    {
        foreach (NodeType::cases() as $case) {
            $class = $case->elementType();
            if ($class !== null) {
                self::assertTrue(class_exists($class), "{$class} is not autoloadable");
            }
        }
    }
}
