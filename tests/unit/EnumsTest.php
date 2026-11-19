<?php

declare(strict_types=1);

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Entry\Elements\Entry;
use JustinHoltWeb\FreeNav\Enums\NodeType;
use JustinHoltWeb\FreeNav\Enums\Preset;

it('keeps the FreeNav 5 node type values', function () {
    expect(array_column(NodeType::cases(), 'value'))
        ->toBe(['entry', 'category', 'asset', 'product', 'custom', 'passive', 'site']);
});

it('labels and colours every node type', function () {
    foreach (NodeType::cases() as $case) {
        expect($case->label())->not->toBe('')
            ->and($case->color())->toMatch('/^#[0-9a-fA-F]{6}$/');
    }
});

it('gives only passive nodes no URL', function () {
    foreach (NodeType::cases() as $case) {
        expect($case->hasUrl())->toBe($case !== NodeType::Passive);
    }
});

it('maps element node types to Craft 6 classes', function () {
    expect(NodeType::Entry->elementType())->toBe(Entry::class)
        ->and(NodeType::Asset->elementType())->toBe(Asset::class)
        ->and(NodeType::Category->elementType())->toBeNull()
        ->and(NodeType::Custom->elementType())->toBeNull()
        ->and(NodeType::Entry->isElement())->toBeTrue()
        ->and(NodeType::Custom->isElement())->toBeFalse();
});

it('never offers categories, and offers products only with Commerce', function () {
    expect(NodeType::Category->isAvailable())->toBeFalse()
        ->and(NodeType::Entry->isAvailable())->toBeTrue()
        ->and(NodeType::Product->isAvailable())->toBe(class_exists(NodeType::COMMERCE_PRODUCT_CLASS));
});

it('ships a template for every preset', function () {
    foreach (Preset::cases() as $preset) {
        expect($preset->templatePath())->toBe('_freenav/_presets/_'.$preset->value)
            ->and(__DIR__.'/../../resources/site/_presets/'.$preset->templateName().'.twig')->toBeFile();
    }
});
