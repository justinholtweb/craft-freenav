<?php

declare(strict_types=1);

use JustinHoltWeb\FreeNav\Elements\Conditions\EntryTypeConditionRule;
use JustinHoltWeb\FreeNav\Elements\Conditions\LoggedInConditionRule;
use JustinHoltWeb\FreeNav\Elements\Conditions\NodeVisibilityCondition;
use JustinHoltWeb\FreeNav\Elements\Conditions\UrlPathConditionRule;
use JustinHoltWeb\FreeNav\Elements\Conditions\UserGroupConditionRule;

it('builds the condition builder’s config from stored rules', function () {
    $config = NodeVisibilityCondition::configFromRules([
        ['type' => 'loggedIn', 'operator' => 'is', 'value' => true],
        ['type' => 'loggedIn', 'operator' => 'isNot', 'value' => true],
        ['type' => 'userGroup', 'operator' => 'is', 'value' => 'members'],
        ['type' => 'entryType', 'operator' => 'isNot', 'value' => ['page', 'article']],
        ['type' => 'urlSegment', 'operator' => 'startsWith', 'value' => 'shop'],
        ['type' => 'custom', 'operator' => 'is', 'value' => 'x'],
    ]);

    expect($config['class'])->toBe(NodeVisibilityCondition::class)
        ->and($config['conditionRules']['operator'])->toBe('and')
        ->and($config['conditionRules']['rules'])->toBe([
            ['class' => LoggedInConditionRule::class, 'value' => true],
            ['class' => LoggedInConditionRule::class, 'value' => false],
            ['class' => UserGroupConditionRule::class, 'operator' => 'in', 'values' => ['members']],
            ['class' => EntryTypeConditionRule::class, 'operator' => 'ni', 'values' => ['page', 'article']],
            ['class' => UrlPathConditionRule::class, 'operator' => 'bw', 'value' => 'shop'],
        ]);
});

it('reads what the builder posts back into stored rules', function () {
    $posted = [
        'class' => NodeVisibilityCondition::class,
        'conditionRules' => ['operator' => 'and', 'rules' => [
            ['class' => LoggedInConditionRule::class, 'uid' => 'a', 'value' => '1'],
            ['class' => UserGroupConditionRule::class, 'uid' => 'b', 'operator' => 'in', 'values' => ['guests', 'members', '']],
            ['type' => json_encode(['class' => EntryTypeConditionRule::class]), 'uid' => 'c', 'operator' => 'ni', 'values' => ['page']],
            ['class' => UrlPathConditionRule::class, 'uid' => 'd', 'operator' => '**', 'value' => '/team'],
        ]],
    ];

    expect(NodeVisibilityCondition::rulesFromConfig($posted))->toBe(['rules' => [
        ['type' => 'loggedIn', 'operator' => 'is', 'value' => true],
        ['type' => 'userGroup', 'operator' => 'is', 'value' => ['guests', 'members']],
        ['type' => 'entryType', 'operator' => 'isNot', 'value' => ['page']],
        ['type' => 'urlSegment', 'operator' => 'contains', 'value' => 'team'],
    ], 'grouped' => false]);
});

it('round-trips stored rules through the builder', function () {
    $rules = [
        ['type' => 'loggedIn', 'operator' => 'is', 'value' => false],
        ['type' => 'userGroup', 'operator' => 'isNot', 'value' => ['staff']],
        ['type' => 'urlSegment', 'operator' => 'is', 'value' => 'about'],
    ];

    expect(NodeVisibilityCondition::rulesFromConfig(NodeVisibilityCondition::configFromRules($rules))['rules'])->toBe($rules);
});

it('accepts the builder’s config as JSON', function () {
    $json = json_encode(NodeVisibilityCondition::configFromRules([['type' => 'loggedIn', 'operator' => 'is', 'value' => true]]));

    expect(NodeVisibilityCondition::rulesFromConfig($json)['rules'])->toBe([['type' => 'loggedIn', 'operator' => 'is', 'value' => true]]);
});

it('only takes FreeNav’s own rule classes', function () {
    $posted = ['conditionRules' => ['operator' => 'and', 'rules' => [
        ['class' => 'CraftCms\\Cms\\Element\\Conditions\\SlugConditionRule', 'operator' => '=', 'value' => 'x'],
        ['type' => json_encode(['class' => 'Some\\Other\\Rule']), 'value' => 'x'],
        ['class' => LoggedInConditionRule::class, 'value' => false],
    ]]];

    expect(NodeVisibilityCondition::rulesFromConfig($posted)['rules'])->toBe([
        ['type' => 'loggedIn', 'operator' => 'is', 'value' => false],
    ]);
});

it('drops rules that are not finished', function () {
    $posted = ['conditionRules' => ['operator' => 'and', 'rules' => [
        ['class' => UserGroupConditionRule::class, 'operator' => 'in', 'values' => []],
        ['class' => UrlPathConditionRule::class, 'operator' => '=', 'value' => '  '],
        ['class' => EntryTypeConditionRule::class, 'operator' => 'notempty', 'values' => ['page']],
    ]]];

    expect(NodeVisibilityCondition::rulesFromConfig($posted)['rules'])->toBe([]);
});

it('refuses OR and nested groups', function () {
    $or = ['conditionRules' => ['operator' => 'or', 'rules' => [
        ['class' => LoggedInConditionRule::class, 'value' => true],
        ['class' => UrlPathConditionRule::class, 'operator' => '=', 'value' => 'about'],
    ]]];
    $nested = ['conditionRules' => ['operator' => 'and', 'rules' => [
        ['operator' => 'and', 'rules' => [['class' => LoggedInConditionRule::class, 'value' => true]]],
    ]]];

    expect(NodeVisibilityCondition::rulesFromConfig($or))->toBe(['rules' => [], 'grouped' => true])
        ->and(NodeVisibilityCondition::rulesFromConfig($nested))->toBe(['rules' => [], 'grouped' => true]);
});

it('knows which stored rules the builder can show', function () {
    expect(NodeVisibilityCondition::isEditable(['type' => 'loggedIn', 'operator' => 'is', 'value' => true]))->toBeTrue()
        ->and(NodeVisibilityCondition::isEditable(['type' => 'userGroup', 'operator' => 'is', 'value' => ['a', 'b']]))->toBeTrue()
        ->and(NodeVisibilityCondition::isEditable(['type' => 'userGroup', 'operator' => 'contains', 'value' => 'a']))->toBeFalse()
        ->and(NodeVisibilityCondition::isEditable(['type' => 'custom', 'operator' => 'is', 'value' => 'a']))->toBeFalse()
        ->and(NodeVisibilityCondition::isEditable(['type' => 'urlSegment', 'operator' => 'is', 'value' => ['a']]))->toBeFalse();
});

it('keeps what the builder posted, minus anything that isn’t FreeNav’s, for redisplay', function () {
    $posted = ['class' => 'Evil\\Condition', 'conditionRules' => ['operator' => 'or', 'rules' => [
        ['class' => UserGroupConditionRule::class, 'uid' => 'a', 'operator' => 'in', 'values' => []],
        ['class' => 'Evil\\Rule', 'uid' => 'b', 'value' => 'x'],
        ['operator' => 'and', 'rules' => [['type' => json_encode(['class' => UrlPathConditionRule::class]), 'uid' => 'c', 'operator' => '=', 'value' => '', 'condition' => 'x']]],
    ]]];

    expect(NodeVisibilityCondition::sanitizeConfig($posted))->toBe([
        'class' => NodeVisibilityCondition::class,
        'conditionRules' => ['operator' => 'or', 'rules' => [
            ['class' => UserGroupConditionRule::class, 'uid' => 'a', 'operator' => 'in', 'values' => []],
            ['operator' => 'and', 'rules' => [['class' => UrlPathConditionRule::class, 'uid' => 'c', 'operator' => '=', 'value' => '']]],
        ]],
    ]);
});
