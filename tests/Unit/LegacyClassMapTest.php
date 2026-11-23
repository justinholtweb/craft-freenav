<?php

declare(strict_types=1);

use JustinHoltWeb\FreeNav\Elements\Conditions\NodeCondition;
use JustinHoltWeb\FreeNav\Elements\Conditions\NodeTypeConditionRule;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\FieldLayoutElements\CustomAttributesField;
use JustinHoltWeb\FreeNav\Fields\MenuField;
use JustinHoltWeb\FreeNav\Helpers\LegacyClassMap;

/**
 * The upgrade migration's class map and the walker it runs over stored values.
 */
it('maps every FreeNav 5 class that Craft stores to a class that exists', function () {
    expect(LegacyClassMap::MAP)->toHaveCount(11);

    foreach (LegacyClassMap::MAP as $old => $new) {
        expect($old)->toStartWith('justinholt\\freenav\\')
            ->and(class_exists($new))->toBeTrue("{$new} does not exist");
    }
});

it('covers the element, field, conditions and the seven layout elements', function () {
    expect(LegacyClassMap::MAP)
        ->toHaveKey('justinholt\\freenav\\elements\\Node')
        ->toHaveKey('justinholt\\freenav\\fields\\MenuField')
        ->toHaveKey('justinholt\\freenav\\elements\\conditions\\NodeCondition')
        ->toHaveKey('justinholt\\freenav\\elements\\conditions\\NodeTypeConditionRule');

    $layoutElements = array_filter(array_keys(LegacyClassMap::MAP), fn ($old) => str_contains($old, 'fieldlayoutelements'));
    expect($layoutElements)->toHaveCount(7);
});

it('replaces exact values only', function () {
    expect(LegacyClassMap::replace('justinholt\\freenav\\elements\\Node'))->toBe(Node::class)
        ->and(LegacyClassMap::replace('justinholt\\freenav\\elements\\Nodes'))->toBe('justinholt\\freenav\\elements\\Nodes')
        ->and(LegacyClassMap::replace('craft\\elements\\Entry'))->toBe('craft\\elements\\Entry')
        ->and(LegacyClassMap::replace(42))->toBe(42)
        ->and(LegacyClassMap::replace(null))->toBeNull();
});

it('walks project config, keys and assoc pairs included', function () {
    $config = [
        'fields' => ['abc' => ['type' => 'justinholt\\freenav\\fields\\MenuField', 'name' => 'Menu']],
        'freeNav' => ['menus' => ['uid' => ['fieldLayout' => ['tabs' => [['elements' => [
            ['type' => 'craft\\fieldlayoutelements\\TitleField'],
            ['type' => 'justinholt\\freenav\\fieldlayoutelements\\CustomAttributesField', 'uid' => 'x'],
        ]]]]]]],
        'elementSources' => ['justinholt\\freenav\\elements\\Node' => [['key' => 'menu:1']]],
        'pairs' => ['__assoc__' => [['justinholt\\freenav\\elements\\Node', 'kept']]],
    ];

    $updated = LegacyClassMap::replace($config);

    expect($updated['fields']['abc']['type'])->toBe(MenuField::class)
        ->and($updated['freeNav']['menus']['uid']['fieldLayout']['tabs'][0]['elements'][0]['type'])->toBe('craft\\fieldlayoutelements\\TitleField')
        ->and($updated['freeNav']['menus']['uid']['fieldLayout']['tabs'][0]['elements'][1]['type'])->toBe(CustomAttributesField::class)
        ->and($updated['elementSources'])->toHaveKey(Node::class)
        ->and($updated['elementSources'])->not->toHaveKey('justinholt\\freenav\\elements\\Node')
        ->and($updated['pairs']['__assoc__'][0])->toBe([Node::class, 'kept']);
});

it('rewrites class names inside JSON held in a string, and leaves other JSON alone', function () {
    $condition = json_encode([
        'class' => 'justinholt\\freenav\\elements\\conditions\\NodeCondition',
        'conditionRules' => [['class' => 'justinholt\\freenav\\elements\\conditions\\NodeTypeConditionRule', 'values' => ['custom']]],
    ]);

    $updated = json_decode((string) LegacyClassMap::replace($condition), true);

    expect($updated['class'])->toBe(NodeCondition::class)
        ->and($updated['conditionRules'][0]['class'])->toBe(NodeTypeConditionRule::class)
        ->and(LegacyClassMap::replace('{"a": 1.0}'))->toBe('{"a": 1.0}');
});

it('is idempotent', function () {
    $once = LegacyClassMap::replace(['type' => 'justinholt\\freenav\\elements\\Node']);

    expect(LegacyClassMap::replace($once))->toBe($once);
});
