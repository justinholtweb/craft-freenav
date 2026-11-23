<?php

declare(strict_types=1);

use JustinHoltWeb\FreeNav\Models\VisibilityRule;

it('has the FreeNav 5 defaults', function () {
    $rule = new VisibilityRule;

    expect($rule->type)->toBe('')
        ->and($rule->operator)->toBe('is')
        ->and($rule->value)->toBeNull();
});

it('knows its types and operators', function () {
    expect(VisibilityRule::TYPES)->toBe(['userGroup', 'loggedIn', 'urlSegment', 'entryType', 'custom'])
        ->and(VisibilityRule::OPERATORS)->toBe(['is', 'isNot', 'contains', 'startsWith']);
});

it('reports unknown types and operators', function () {
    expect(VisibilityRule::fromArray(['type' => 'loggedIn', 'operator' => 'is', 'value' => true])->problems())->toBe([])
        ->and(VisibilityRule::fromArray(['type' => 'moonPhase'])->problems())->toHaveCount(1)
        ->and(VisibilityRule::fromArray(['type' => 'loggedIn', 'operator' => 'near'])->problems())->toHaveCount(1);
});

it('lets an unknown type through rather than hiding the node', function () {
    expect(VisibilityRule::fromArray(['type' => 'moonPhase'])->evaluate())->toBeTrue()
        ->and((new VisibilityRule)->evaluate())->toBeTrue();
});

it('matches logged-in state', function () {
    $isIn = VisibilityRule::fromArray(['type' => 'loggedIn', 'operator' => 'is', 'value' => true]);
    $isNotIn = VisibilityRule::fromArray(['type' => 'loggedIn', 'operator' => 'isNot', 'value' => true]);

    expect($isIn->evaluateLoggedIn(true))->toBeTrue()
        ->and($isIn->evaluateLoggedIn(false))->toBeFalse()
        ->and($isNotIn->evaluateLoggedIn(false))->toBeTrue();
});

it('matches URL segments', function (string $operator, string $value, string $path, bool $expected) {
    expect(VisibilityRule::fromArray(['type' => 'urlSegment', 'operator' => $operator, 'value' => $value])->evaluateUrlSegment($path))
        ->toBe($expected);
})->with([
    ['is', 'about', 'about', true],
    ['is', 'about', 'about/team', false],
    ['isNot', 'about', 'blog', true],
    ['contains', 'team', 'about/team', true],
    ['startsWith', 'shop', 'shop/cart', true],
    ['startsWith', 'shop', 'workshop', false],
]);

it('treats guests as a user group', function () {
    expect(VisibilityRule::fromArray(['type' => 'userGroup', 'operator' => 'is', 'value' => 'guests'])->evaluateUserGroup(null))->toBeTrue()
        ->and(VisibilityRule::fromArray(['type' => 'userGroup', 'operator' => 'is', 'value' => 'members'])->evaluateUserGroup(null))->toBeFalse()
        ->and(VisibilityRule::fromArray(['type' => 'userGroup', 'operator' => 'isNot', 'value' => 'members'])->evaluateUserGroup(null))->toBeTrue();
});

it('treats a page that is not an entry as no entry type', function () {
    expect(VisibilityRule::fromArray(['type' => 'entryType', 'operator' => 'is', 'value' => 'page'])->evaluateEntryType(false))->toBeFalse()
        ->and(VisibilityRule::fromArray(['type' => 'entryType', 'operator' => 'isNot', 'value' => 'page'])->evaluateEntryType(false))->toBeTrue();
});

it('takes a list of user groups', function () {
    $guestsOrMembers = VisibilityRule::fromArray(['type' => 'userGroup', 'operator' => 'is', 'value' => ['members', 'guests']]);
    $notGuests = VisibilityRule::fromArray(['type' => 'userGroup', 'operator' => 'isNot', 'value' => ['guests']]);
    $membersOnly = VisibilityRule::fromArray(['type' => 'userGroup', 'operator' => 'is', 'value' => ['members']]);
    $notMembers = VisibilityRule::fromArray(['type' => 'userGroup', 'operator' => 'isNot', 'value' => ['members', 'staff']]);

    expect($guestsOrMembers->evaluateUserGroup(null))->toBeTrue()
        ->and($notGuests->evaluateUserGroup(null))->toBeFalse()
        ->and($membersOnly->evaluateUserGroup(null))->toBeFalse()
        ->and($notMembers->evaluateUserGroup(null))->toBeTrue()
        ->and($guestsOrMembers->problems())->toBe([]);
});

it('takes a list of entry types', function (string $operator, array $handles, ?string $page, bool $expected) {
    expect(VisibilityRule::fromArray(['type' => 'entryType', 'operator' => $operator, 'value' => $handles])->evaluateEntryTypeHandle($page))
        ->toBe($expected);
})->with([
    ['is', ['page', 'article'], 'article', true],
    ['is', ['page', 'article'], 'news', false],
    ['is', ['page'], null, false],
    ['isNot', ['page', 'article'], 'news', true],
    ['isNot', ['page', 'article'], 'page', false],
    ['isNot', ['page'], null, true],
]);

it('still takes a single entry type', function () {
    expect(VisibilityRule::fromArray(['type' => 'entryType', 'operator' => 'is', 'value' => 'page'])->evaluateEntryTypeHandle('page'))->toBeTrue()
        ->and(VisibilityRule::fromArray(['type' => 'entryType', 'operator' => 'isNot', 'value' => 'page'])->evaluateEntryTypeHandle('page'))->toBeFalse();
});

it('lets an empty list through', function () {
    expect(VisibilityRule::fromArray(['type' => 'userGroup', 'operator' => 'is', 'value' => []])->evaluateUserGroup(null))->toBeTrue()
        ->and(VisibilityRule::fromArray(['type' => 'entryType', 'operator' => 'is', 'value' => []])->evaluateEntryTypeHandle('page'))->toBeTrue();
});

it('only takes lists of handles for the types that use them', function () {
    expect(VisibilityRule::fromArray(['type' => 'urlSegment', 'operator' => 'is', 'value' => ['about']])->problems())->toHaveCount(1)
        ->and(VisibilityRule::fromArray(['type' => 'userGroup', 'operator' => 'is', 'value' => [['nested']]])->problems())->toHaveCount(1)
        ->and(VisibilityRule::fromArray(['type' => 'entryType', 'operator' => 'is', 'value' => ['a' => 'page']])->problems())->toHaveCount(1)
        ->and(VisibilityRule::fromArray(['type' => 'entryType', 'operator' => 'is', 'value' => ['page', 'article']])->problems())->toBe([]);
});
