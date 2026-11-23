<?php

namespace justinholt\freenav\elements\conditions\visibility;

use Craft;
use craft\base\conditions\BaseTextConditionRule;

class UrlPathConditionRule extends BaseTextConditionRule implements VisibilityRuleInterface
{
    private const OPERATOR_MAP = [
        self::OPERATOR_EQ => 'is',
        self::OPERATOR_NE => 'isNot',
        self::OPERATOR_CONTAINS => 'contains',
        self::OPERATOR_BEGINS_WITH => 'startsWith',
    ];

    public function getLabel(): string
    {
        return Craft::t('free-nav', 'URL Path');
    }

    public function getLabelHint(): ?string
    {
        return Craft::t('free-nav', 'The page’s path, without the leading slash');
    }

    public static function ruleType(): string
    {
        return 'urlSegment';
    }

    protected function operators(): array
    {
        return array_keys(self::OPERATOR_MAP);
    }

    public static function configFromRule(array $rule): ?array
    {
        $operator = array_search($rule['operator'] ?? 'is', self::OPERATOR_MAP, true);
        $value = $rule['value'] ?? '';

        if ($operator === false || !is_scalar($value)) {
            return null;
        }

        return ['operator' => $operator, 'value' => (string)$value];
    }

    public function toRule(): ?array
    {
        // Left empty, the rule is unfinished; Craft's own conditions skip those too.
        if (!isset(self::OPERATOR_MAP[$this->operator]) || trim($this->value) === '') {
            return null;
        }

        return ['type' => 'urlSegment', 'operator' => self::OPERATOR_MAP[$this->operator], 'value' => ltrim(trim($this->value), '/')];
    }
}
