<?php

declare(strict_types=1);

namespace justinholt\freenav\tests\unit;

use justinholt\freenav\elements\conditions\visibility\LoggedInConditionRule;
use justinholt\freenav\elements\conditions\visibility\NodeVisibilityCondition;
use justinholt\freenav\elements\conditions\visibility\UrlPathConditionRule;
use justinholt\freenav\elements\conditions\visibility\UserGroupConditionRule;
use PHPUnit\Framework\TestCase;

/**
 * Stored rules → builder rule configs, and which stored rules the builder leaves alone.
 */
final class NodeVisibilityConditionTest extends TestCase
{
    public function testStoredRulesBecomeBuilderConfigs(): void
    {
        self::assertSame(['value' => false], LoggedInConditionRule::configFromRule(['type' => 'loggedIn', 'operator' => 'isNot', 'value' => true]));
        self::assertSame(['operator' => 'ni', 'values' => ['guests', 'staff']], UserGroupConditionRule::configFromRule(['type' => 'userGroup', 'operator' => 'isNot', 'value' => ['guests', 'staff']]));
        self::assertSame(['operator' => 'in', 'values' => ['members']], UserGroupConditionRule::configFromRule(['type' => 'userGroup', 'operator' => 'is', 'value' => 'members']));
        self::assertSame(['operator' => 'bw', 'value' => 'blog'], UrlPathConditionRule::configFromRule(['type' => 'urlSegment', 'operator' => 'startsWith', 'value' => 'blog']));
    }

    public function testRulesTheBuilderCantShowAreKept(): void
    {
        $rules = [
            ['type' => 'loggedIn', 'operator' => 'is', 'value' => true],
            ['type' => 'custom', 'operator' => 'is', 'value' => 'x'],
            ['type' => 'userGroup', 'operator' => 'contains', 'value' => 'staff'],
            ['type' => 'urlSegment', 'operator' => 'endsWith', 'value' => 'x'],
            'not a rule',
        ];

        self::assertSame(array_slice($rules, 1), NodeVisibilityCondition::rulesItCantShow($rules));
    }
}
