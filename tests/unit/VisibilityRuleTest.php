<?php

declare(strict_types=1);

namespace justinholt\freenav\tests\unit;

use justinholt\freenav\models\VisibilityRule;
use PHPUnit\Framework\TestCase;

/**
 * Tests the parts of VisibilityRule that don't depend on a running Craft
 * request/user: its validation ranges and the evaluate() fall-through.
 */
final class VisibilityRuleTest extends TestCase
{
    public function testDefaults(): void
    {
        $rule = new VisibilityRule();
        self::assertSame('', $rule->type);
        self::assertSame('is', $rule->operator);
        self::assertNull($rule->value);
    }

    public function testValidTypesAndOperatorsAreConstrained(): void
    {
        $rules = (new VisibilityRule())->defineRules();

        $typeRange = null;
        $operatorRange = null;
        foreach ($rules as $rule) {
            if (($rule[1] ?? null) === 'in' && in_array('type', (array)$rule[0], true)) {
                $typeRange = $rule['range'];
            }
            if (($rule[1] ?? null) === 'in' && in_array('operator', (array)$rule[0], true)) {
                $operatorRange = $rule['range'];
            }
        }

        self::assertSame(
            ['userGroup', 'loggedIn', 'urlSegment', 'entryType', 'custom'],
            $typeRange,
        );
        self::assertSame(['is', 'isNot', 'contains', 'startsWith'], $operatorRange);
    }

    public function testUnknownTypeEvaluatesTrue(): void
    {
        $rule = new VisibilityRule();
        $rule->type = 'custom';
        self::assertTrue($rule->evaluate());

        $rule->type = '';
        self::assertTrue($rule->evaluate());
    }
}
