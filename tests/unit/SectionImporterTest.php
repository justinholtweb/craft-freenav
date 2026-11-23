<?php

declare(strict_types=1);

namespace justinholt\freenav\tests\unit;

use justinholt\freenav\services\SectionImporter;
use PHPUnit\Framework\TestCase;

/**
 * The parts of the section importer that don't need entries: reading a link field's value
 * and nesting entries by level.
 */
final class SectionImporterTest extends TestCase
{
    public function testNoLinkIsPassive(): void
    {
        self::assertSame(['nodeType' => 'passive'], SectionImporter::linkFromValue(null));
        self::assertSame(['nodeType' => 'passive'], SectionImporter::linkFromValue(''));
        self::assertSame(['nodeType' => 'passive'], SectionImporter::linkFromValue([]));
    }

    public function testStringIsCustomUrl(): void
    {
        self::assertSame(['nodeType' => 'custom', 'customUrl' => 'https://example.com'], SectionImporter::linkFromValue(' https://example.com '));
    }

    public function testLinkObjectWithoutElementUsesItsUrlAndTarget(): void
    {
        $link = new class {
            public function getElement(): mixed
            {
                return null;
            }

            public function getUrl(): string
            {
                return 'mailto:hi@example.com';
            }

            public function getTarget(): ?string
            {
                return '_blank';
            }
        };

        self::assertSame(
            ['nodeType' => 'custom', 'customUrl' => 'mailto:hi@example.com', 'newWindow' => true],
            SectionImporter::linkFromValue($link),
        );
    }

    public function testCraftLinkDataTargetIsAProperty(): void
    {
        $link = new class {
            public ?string $target = '_blank';

            public function getUrl(): string
            {
                return 'https://example.com';
            }
        };

        self::assertSame(
            ['nodeType' => 'custom', 'customUrl' => 'https://example.com', 'newWindow' => true],
            SectionImporter::linkFromValue($link),
        );
    }

    public function testFirstOfAListIsUsed(): void
    {
        self::assertSame(['nodeType' => 'custom', 'customUrl' => '/a'], SectionImporter::linkFromValue(['/a', '/b']));
    }

    public function testTreeNestsByLevel(): void
    {
        $tree = SectionImporter::tree([
            ['level' => 1, 'node' => ['title' => 'A']],
            ['level' => 2, 'node' => ['title' => 'A1']],
            ['level' => 3, 'node' => ['title' => 'A1a']],
            ['level' => 2, 'node' => ['title' => 'A2']],
            ['level' => 1, 'node' => ['title' => 'B']],
        ]);

        self::assertSame([
            ['title' => 'A', 'children' => [
                ['title' => 'A1', 'children' => [
                    ['title' => 'A1a'],
                ]],
                ['title' => 'A2'],
            ]],
            ['title' => 'B'],
        ], $tree);
    }

    public function testSkippedLevelHangsOffNearestAncestor(): void
    {
        $tree = SectionImporter::tree([
            ['level' => 1, 'node' => ['title' => 'A']],
            ['level' => 3, 'node' => ['title' => 'deep']],
        ]);

        self::assertSame([['title' => 'A', 'children' => [['title' => 'deep']]]], $tree);
    }

    public function testTreeDoesNotLeakReferences(): void
    {
        $tree = SectionImporter::tree([
            ['level' => 1, 'node' => ['title' => 'A']],
            ['level' => 2, 'node' => ['title' => 'A1']],
        ]);
        $copy = $tree;
        $copy[0]['children'][0]['title'] = 'changed';

        self::assertSame('A1', $tree[0]['children'][0]['title']);
    }
}
