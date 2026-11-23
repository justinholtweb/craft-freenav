<?php

namespace justinholt\freenav\elements\conditions\visibility;

use craft\base\conditions\BaseCondition;

/**
 * The control panel's editor for a node's visibility rules.
 *
 * It is never stored: `fromRules()` builds it from the node's rules and `toRules()` turns what
 * the editor posted back into them, so the stored JSON, the REST API and export files keep
 * the same `[{type, operator, value}]` shape. Every rule must pass, the same as the builder's
 * own AND.
 */
class NodeVisibilityCondition extends BaseCondition
{
    /** @var array<class-string<VisibilityRuleInterface>> */
    public const RULE_TYPES = [
        LoggedInConditionRule::class,
        UserGroupConditionRule::class,
        UrlPathConditionRule::class,
        EntryTypeConditionRule::class,
    ];

    public function init(): void
    {
        parent::init();
        $this->mainTag = 'div';
        $this->sortable = false;
    }

    protected function selectableConditionRules(): array
    {
        return self::RULE_TYPES;
    }

    public static function fromRules(array $rules): self
    {
        $configs = [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            foreach (self::RULE_TYPES as $class) {
                if (($rule['type'] ?? null) === $class::ruleType() && ($config = $class::configFromRule($rule)) !== null) {
                    $configs[] = ['class' => $class] + $config;
                    break;
                }
            }
        }

        $condition = new self();
        $condition->setConditionRules($configs);

        return $condition;
    }

    /**
     * Builds the condition from what the builder posted. Only this condition's own rule
     * classes are accepted; the posted `class` keys are never instantiated otherwise.
     */
    public static function fromPost(array $post): self
    {
        $configs = [];

        foreach ((array)($post['conditionRules'] ?? []) as $config) {
            if (is_array($config) && in_array($config['class'] ?? null, self::RULE_TYPES, true)) {
                $configs[] = $config;
            }
        }

        $condition = new self();
        $condition->setConditionRules($configs);

        return $condition;
    }

    public function toRules(): array
    {
        $rules = [];

        foreach ($this->getConditionRules() as $rule) {
            if ($rule instanceof VisibilityRuleInterface && ($stored = $rule->toRule()) !== null) {
                $rules[] = $stored;
            }
        }

        return $rules;
    }

    /**
     * Stored rules the builder can't show (such as `custom`), kept as they are when the
     * builder's rules are saved over the rest.
     */
    public static function rulesItCantShow(array $rules): array
    {
        return array_values(array_filter($rules, fn($rule) => !is_array($rule) || !self::canShow($rule)));
    }

    public static function canShow(array $rule): bool
    {
        foreach (self::RULE_TYPES as $class) {
            if (($rule['type'] ?? null) === $class::ruleType()) {
                return $class::configFromRule($rule) !== null;
            }
        }

        return false;
    }
}
