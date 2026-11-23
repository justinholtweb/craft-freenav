<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Elements\Conditions;

use CraftCms\Cms\Condition\BaseCondition;
use CraftCms\Cms\Condition\Contracts\ConditionGroupInterface;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Json;
use Override;

use function CraftCms\Cms\t;

/**
 * The node editor's front end for a node's visibility rules.
 *
 * The condition itself is never stored. A node keeps its rules as `[{type, operator, value}]`
 * (what the renderer, the REST API, GraphQL and export files use); {@see fromRules()} builds the
 * builder from them and {@see rulesFromConfig()} turns what the builder posts back into them.
 *
 * Every rule must pass, so the builder's rules are combined with AND only.
 */
class NodeVisibilityCondition extends BaseCondition
{
    /** Rule class => stored rule type. The only rule classes a posted condition may name. */
    public const array RULE_TYPES = [
        LoggedInConditionRule::class => 'loggedIn',
        UserGroupConditionRule::class => 'userGroup',
        UrlPathConditionRule::class => 'urlSegment',
        EntryTypeConditionRule::class => 'entryType',
    ];

    /** Stored operator => builder operator, for the text rule. */
    private const array TEXT_OPERATORS = ['is' => '=', 'isNot' => '!=', 'contains' => '**', 'startsWith' => 'bw'];

    /** Stored operator => builder operator, for the list rules. */
    private const array LIST_OPERATORS = ['is' => 'in', 'isNot' => 'ni'];

    #[Override]
    public bool $sortable = false;

    public static function createGroup(): ConditionGroupInterface
    {
        return new NodeVisibilityConditionGroup;
    }

    #[Override]
    protected function selectableConditionRules(): array
    {
        return array_keys(self::RULE_TYPES);
    }

    /**
     * @param  array<mixed>  $rules  A node's stored rules
     */
    public static function fromRules(array $rules): self
    {
        $condition = Conditions::createCondition(self::configFromRules($rules));
        assert($condition instanceof self);

        return $condition;
    }

    /**
     * @return list<array{type: string, operator: string, value: mixed}>
     */
    public function toRules(): array
    {
        return self::rulesFromConfig($this->getConfig())['rules'];
    }

    /**
     * Builder config for a node's stored rules. Rules the builder can't show are left out; see
     * {@see isEditable()}.
     *
     * @param  array<mixed>  $rules
     * @return array<string, mixed>
     */
    public static function configFromRules(array $rules): array
    {
        $configs = [];

        foreach ($rules as $rule) {
            if (! is_array($rule) || ! self::isEditable($rule)) {
                continue;
            }

            $operator = (string) ($rule['operator'] ?? 'is');
            $value = $rule['value'] ?? null;

            $configs[] = match ($rule['type']) {
                'loggedIn' => [
                    'class' => LoggedInConditionRule::class,
                    'value' => ($operator === 'is') === (bool) $value,
                ],
                'urlSegment' => [
                    'class' => UrlPathConditionRule::class,
                    'operator' => self::TEXT_OPERATORS[$operator],
                    'value' => is_scalar($value) ? (string) $value : '',
                ],
                default => [
                    'class' => array_search($rule['type'], self::RULE_TYPES, true),
                    'operator' => self::LIST_OPERATORS[$operator],
                    'values' => array_values(array_map(strval(...), (array) $value)),
                ],
            };
        }

        return [
            'class' => self::class,
            'conditionRules' => ['operator' => 'and', 'rules' => $configs],
        ];
    }

    /**
     * Whether the builder can show a stored rule. Anything else (a `custom` rule, a `contains`
     * user group…) is kept as it is when the node is saved.
     *
     * @param  array<mixed>  $rule
     */
    public static function isEditable(array $rule): bool
    {
        $operator = $rule['operator'] ?? 'is';
        $value = $rule['value'] ?? null;

        return match ($rule['type'] ?? null) {
            'loggedIn' => in_array($operator, ['is', 'isNot'], true) && (is_scalar($value) || $value === null),
            'urlSegment' => isset(self::TEXT_OPERATORS[$operator]) && (is_scalar($value) || $value === null),
            'userGroup', 'entryType' => isset(self::LIST_OPERATORS[$operator])
                && (is_string($value) || is_int($value) || (is_array($value) && array_all($value, fn (mixed $v) => is_string($v) || is_int($v)))),
            default => false,
        };
    }

    /**
     * What the builder posted, kept to FreeNav's own rule classes, for showing the builder again
     * as the editor left it (a rule without a value yet, an OR it still has to undo) when the
     * editor refreshes the form. Anything else is dropped before Craft instantiates a class.
     *
     * @return array<string, mixed>
     */
    public static function sanitizeConfig(mixed $config): array
    {
        if (is_string($config)) {
            $config = Json::decodeIfJson($config);
        }

        $group = is_array($config) ? ($config['conditionRules'] ?? []) : [];

        return [
            'class' => self::class,
            'conditionRules' => self::sanitizeGroup(is_array($group) ? $group : []),
        ];
    }

    /**
     * @param  array<mixed>  $group
     * @return array{operator: string, rules: list<array<string, mixed>>}
     */
    private static function sanitizeGroup(array $group, int $depth = 0): array
    {
        $items = isset($group['rules']) || isset($group['operator']) ? ($group['rules'] ?? []) : $group;
        $operator = is_string($group['operator'] ?? null) && strtolower($group['operator']) === 'or' ? 'or' : 'and';
        $rules = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['rules']) && ! isset($item['class']) && ! isset($item['type'])) {
                if ($depth < 4) {
                    $rules[] = self::sanitizeGroup($item, $depth + 1);
                }

                continue;
            }

            $class = $item['class'] ?? null;

            if (isset($item['type'])) {
                $type = is_string($item['type']) ? Json::decodeIfJson($item['type']) : $item['type'];
                $class = is_array($type) ? ($type['class'] ?? null) : $type;
            }

            if (is_string($class) && isset(self::RULE_TYPES[$class])) {
                $rule = ['class' => $class];

                foreach (['uid', 'operator', 'value', 'values'] as $key) {
                    if (array_key_exists($key, $item) && (is_scalar($item[$key]) || is_array($item[$key]))) {
                        $rule[$key] = $item[$key];
                    }
                }

                $rules[] = $rule;
            }
        }

        return ['operator' => $operator, 'rules' => $rules];
    }

    /**
     * Stored rules from what the condition builder posted.
     *
     * The posted config is read as data: no class it names is instantiated, and only
     * {@see RULE_TYPES}' rule classes are taken. `grouped` is true, and no rules are returned, when
     * the config asks for something stored rules can't express (OR, or nested groups); see
     * {@see groupedError()}.
     *
     * @return array{rules: list<array{type: string, operator: string, value: mixed}>, grouped: bool}
     */
    public static function rulesFromConfig(mixed $config): array
    {
        if (is_string($config)) {
            $config = Json::decodeIfJson($config);
        }

        $group = is_array($config) ? ($config['conditionRules'] ?? []) : [];

        if (is_string($group)) {
            $group = Json::decodeIfJson($group);
        }

        if (! is_array($group)) {
            return ['rules' => [], 'grouped' => false];
        }

        $items = isset($group['rules']) || isset($group['operator']) ? ($group['rules'] ?? []) : $group;
        $items = is_array($items) ? array_values($items) : [];
        $operator = strtolower(is_string($group['operator'] ?? null) ? $group['operator'] : 'and');

        if ($operator !== 'and' && count($items) > 1) {
            return ['rules' => [], 'grouped' => true];
        }

        $rules = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['rules']) && ! isset($item['class']) && ! isset($item['type'])) {
                if (is_array($item['rules']) && $item['rules'] !== []) {
                    return ['rules' => [], 'grouped' => true];
                }

                continue;
            }

            $rule = self::ruleFromConfig($item);

            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        return ['rules' => $rules, 'grouped' => false];
    }

    /**
     * @param  array<mixed>  $item
     * @return array{type: string, operator: string, value: mixed}|null
     */
    private static function ruleFromConfig(array $item): ?array
    {
        $class = $item['class'] ?? null;

        // The builder posts a rule's type as its class, or as JSON holding it.
        if (isset($item['type'])) {
            $type = is_string($item['type']) ? Json::decodeIfJson($item['type']) : $item['type'];
            $class = is_array($type) ? ($type['class'] ?? null) : $type;
        }

        if (! is_string($class) || ! isset(self::RULE_TYPES[$class])) {
            return null;
        }

        $ruleType = self::RULE_TYPES[$class];
        $operator = is_string($item['operator'] ?? null) ? $item['operator'] : null;

        if ($ruleType === 'loggedIn') {
            $value = $item['value'] ?? true;

            return ['type' => 'loggedIn', 'operator' => 'is', 'value' => filter_var($value, FILTER_VALIDATE_BOOLEAN)];
        }

        if ($ruleType === 'urlSegment') {
            $value = is_scalar($item['value'] ?? null) ? trim((string) $item['value']) : '';
            $stored = array_search($operator ?? '=', self::TEXT_OPERATORS, true);

            // An empty value matches every page in Craft's own text rules, so it isn't a rule yet.
            return $value === '' || $stored === false ? null : ['type' => 'urlSegment', 'operator' => $stored, 'value' => ltrim($value, '/')];
        }

        $values = $item['values'] ?? [];
        $values = is_array($values) ? $values : ($values === '' ? [] : [$values]);
        $values = array_values(array_unique(array_filter(
            array_map(fn (mixed $v) => is_scalar($v) ? trim((string) $v) : '', $values),
            fn (string $v) => $v !== '',
        )));
        $stored = array_search($operator ?? 'in', self::LIST_OPERATORS, true);

        return $values === [] || $stored === false ? null : ['type' => $ruleType, 'operator' => $stored, 'value' => $values];
    }

    public static function groupedError(): string
    {
        return t('A node is shown only when every visibility rule passes, so rules can’t be combined with OR or grouped.', category: 'free-nav');
    }
}
