<?php

namespace justinholt\freenav\elements\conditions\visibility;

use justinholt\freenav\models\VisibilityRule;

/**
 * For the multi-select rules: `in` / `not in` handles ↔ `is` / `isNot` with a list of handles.
 */
trait MultiSelectRuleTrait
{
    public static function configFromRule(array $rule): ?array
    {
        if (!in_array($rule['operator'] ?? 'is', ['is', 'isNot'], true)) {
            return null;
        }

        $handles = (new VisibilityRule(['type' => static::ruleType(), 'value' => $rule['value'] ?? null]))->getHandles();

        return [
            'operator' => ($rule['operator'] ?? 'is') === 'isNot' ? self::OPERATOR_NOT_IN : self::OPERATOR_IN,
            'values' => $handles,
        ];
    }

    public function toRule(): ?array
    {
        $values = array_values(array_filter($this->getValues(), fn($v) => is_string($v) && $v !== ''));

        if ($values === []) {
            return null;
        }

        return [
            'type' => static::ruleType(),
            'operator' => $this->operator === self::OPERATOR_NOT_IN ? 'isNot' : 'is',
            'value' => $values,
        ];
    }
}
