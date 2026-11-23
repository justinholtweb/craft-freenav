<?php

namespace justinholt\freenav\elements\conditions\visibility;

use Craft;
use craft\base\conditions\BaseLightswitchConditionRule;

class LoggedInConditionRule extends BaseLightswitchConditionRule implements VisibilityRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('free-nav', 'Logged In');
    }

    public static function ruleType(): string
    {
        return 'loggedIn';
    }

    public static function configFromRule(array $rule): ?array
    {
        if (!in_array($rule['operator'] ?? 'is', ['is', 'isNot'], true) || !(is_scalar($rule['value'] ?? null) || ($rule['value'] ?? null) === null)) {
            return null;
        }

        $value = (bool)($rule['value'] ?? false);

        return ['value' => ($rule['operator'] ?? 'is') === 'isNot' ? !$value : $value];
    }

    public function toRule(): ?array
    {
        return ['type' => 'loggedIn', 'operator' => 'is', 'value' => $this->value];
    }
}
