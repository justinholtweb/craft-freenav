<?php

namespace justinholt\freenav\elements\conditions\visibility;

/**
 * A condition rule that stands for one stored visibility rule (`{type, operator, value}`).
 */
interface VisibilityRuleInterface
{
    /**
     * The stored rule type this condition rule edits.
     */
    public static function ruleType(): string;

    /**
     * The condition rule's config for a stored rule, or null when it can't show it.
     */
    public static function configFromRule(array $rule): ?array;

    /**
     * The stored rule, or null when the condition rule isn't filled in.
     */
    public function toRule(): ?array;
}
