<?php

declare(strict_types=1);

use JustinHoltWeb\FreeNav\Services\SectionImporter;

it('nests a structure-ordered list by level', function () {
    $items = [
        ['level' => 1, 'node' => ['title' => 'Main']],
        ['level' => 2, 'node' => ['title' => 'About']],
        ['level' => 3, 'node' => ['title' => 'Team']],
        ['level' => 2, 'node' => ['title' => 'Blog']],
        ['level' => 1, 'node' => ['title' => 'Footer']],
        ['level' => 2, 'node' => ['title' => 'Privacy']],
    ];

    expect(SectionImporter::tree($items))->toBe([
        ['title' => 'Main', 'children' => [
            ['title' => 'About', 'children' => [['title' => 'Team']]],
            ['title' => 'Blog'],
        ]],
        ['title' => 'Footer', 'children' => [['title' => 'Privacy']]],
    ]);
});

it('turns a URL into a custom node', function () {
    expect(SectionImporter::linkFromValue('https://example.com/'))->toBe(['nodeType' => 'custom', 'customUrl' => 'https://example.com/']);
});

it('turns no link into a passive node', function () {
    expect(SectionImporter::linkFromValue(null))->toBe(['nodeType' => 'passive'])
        ->and(SectionImporter::linkFromValue(''))->toBe(['nodeType' => 'passive'])
        ->and(SectionImporter::linkFromValue([]))->toBe(['nodeType' => 'passive']);
});

it('reads link objects by shape', function () {
    $link = new class
    {
        public ?string $target = '_blank';

        public function getElement(): mixed
        {
            return null;
        }

        public function getUrl(): string
        {
            return 'mailto:hi@example.com';
        }
    };

    $legacy = new class
    {
        public function getTarget(): string
        {
            return '';
        }

        public function getUrl(): ?string
        {
            return null;
        }
    };

    expect(SectionImporter::linkFromValue($link))->toBe(['newWindow' => true, 'nodeType' => 'custom', 'customUrl' => 'mailto:hi@example.com'])
        ->and(SectionImporter::linkFromValue($legacy))->toBe(['nodeType' => 'passive']);
});
