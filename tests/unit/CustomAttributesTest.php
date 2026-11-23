<?php

declare(strict_types=1);

namespace justinholt\freenav\tests\unit;

use justinholt\freenav\elements\Node;
use PHPUnit\Framework\TestCase;

/**
 * The editor's Key / Value table rows → the stored `[{key, value}]` list.
 */
final class CustomAttributesTest extends TestCase
{
    public function testTableRowsBecomeAList(): void
    {
        self::assertSame(
            [['key' => 'data-track', 'value' => 'nav'], ['key' => 'rel', 'value' => '']],
            Node::normalizeCustomAttributes([
                '0' => ['key' => ' data-track ', 'value' => 'nav'],
                'new1' => ['key' => '', 'value' => 'dropped'],
                'new2' => ['key' => 'rel'],
            ]),
        );
    }

    public function testJsonIsStillAccepted(): void
    {
        self::assertSame([['key' => 'title', 'value' => 'Hi']], Node::normalizeCustomAttributes('[{"key":"title","value":"Hi"}]'));
    }

    public function testEmptyMeansNone(): void
    {
        self::assertNull(Node::normalizeCustomAttributes(''));
        self::assertNull(Node::normalizeCustomAttributes([]));
        self::assertNull(Node::normalizeCustomAttributes([['key' => '', 'value' => 'x']]));
    }

    public function testTextThatIsntJsonIsKeptForValidation(): void
    {
        self::assertSame('not json', Node::normalizeCustomAttributes('not json'));
    }

    public function testCraftIconNamesMustLookLikeNames(): void
    {
        foreach (['../../etc/passwd', 'House', 'house.svg', '<svg>', '-house', 'house-'] as $name) {
            self::assertFalse(Node::isAllowedCraftIcon($name), $name);
        }
    }

    public function testSafeCustomAttributesDropUnsafeNames(): void
    {
        // No constructor: a Node normally needs a running Craft app.
        $node = (new \ReflectionClass(Node::class))->newInstanceWithoutConstructor();
        $node->customAttributes = [
            ['key' => 'data-track', 'value' => 'nav'],
            ['key' => 'onclick', 'value' => 'alert(1)'],
            ['key' => 'rel', 'value' => 'nofollow'],
            'garbage',
        ];

        self::assertSame(['data-track' => 'nav', 'rel' => 'nofollow'], $node->getSafeCustomAttributes());
    }
}
