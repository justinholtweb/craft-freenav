<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Models;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Route\MatchedElement;
use CraftCms\Cms\User\Elements\User;

use function CraftCms\Cms\currentUserElement;

/**
 * One rule from a node's visibility rules: `{"type": "loggedIn", "operator": "is", "value": true}`.
 *
 * A node is shown only when every rule passes. A type or operator FreeNav doesn't know passes, so a
 * rule written for a newer version never hides a node by accident.
 */
class VisibilityRule
{
    public const array TYPES = ['userGroup', 'loggedIn', 'urlSegment', 'entryType', 'custom'];

    public const array OPERATORS = ['is', 'isNot', 'contains', 'startsWith'];

    public function __construct(
        public string $type = '',
        public string $operator = 'is',
        public mixed $value = null,
    ) {}

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: is_scalar($data['type'] ?? null) ? (string) $data['type'] : '',
            operator: is_scalar($data['operator'] ?? null) ? (string) $data['operator'] : 'is',
            value: $data['value'] ?? null,
        );
    }

    /**
     * Problems with the rule, for the node editor. Empty when it is valid.
     *
     * @return string[]
     */
    public function problems(): array
    {
        $problems = [];

        if (! in_array($this->type, self::TYPES, true)) {
            $problems[] = sprintf('Unknown rule type “%s”. Use one of: %s.', $this->type, implode(', ', self::TYPES));
        }

        if (! in_array($this->operator, self::OPERATORS, true)) {
            $problems[] = sprintf('Unknown operator “%s”. Use one of: %s.', $this->operator, implode(', ', self::OPERATORS));
        }

        return $problems;
    }

    public function evaluate(): bool
    {
        return match ($this->type) {
            'loggedIn' => $this->evaluateLoggedIn(! self::isConsole() && currentUserElement() !== null),
            'userGroup' => $this->evaluateUserGroup(self::isConsole() ? null : currentUserElement()),
            'urlSegment' => self::isConsole() || $this->evaluateUrlSegment(request()->craftPath()),
            'entryType' => $this->evaluateEntryType(self::isConsole() ? false : MatchedElement::get()),
            default => true,
        };
    }

    public function evaluateLoggedIn(bool $isLoggedIn): bool
    {
        return match ($this->operator) {
            'is' => $isLoggedIn === (bool) $this->value,
            'isNot' => $isLoggedIn !== (bool) $this->value,
            default => true,
        };
    }

    public function evaluateUserGroup(?User $user): bool
    {
        if ($this->value === 'guests') {
            return match ($this->operator) {
                'is' => $user === null,
                'isNot' => $user !== null,
                default => true,
            };
        }

        if ($user === null) {
            return $this->operator === 'isNot';
        }

        $isInGroup = is_string($this->value) || is_int($this->value) ? $user->isInGroup($this->value) : false;

        return match ($this->operator) {
            'is' => $isInGroup,
            'isNot' => ! $isInGroup,
            default => true,
        };
    }

    /**
     * @param  string  $path  The request path, without leading or trailing slashes (`about/team`).
     */
    public function evaluateUrlSegment(string $path): bool
    {
        $value = is_scalar($this->value) ? (string) $this->value : '';

        return match ($this->operator) {
            'is' => $path === $value,
            'isNot' => $path !== $value,
            'contains' => str_contains($path, $value),
            'startsWith' => str_starts_with($path, $value),
            default => true,
        };
    }

    public function evaluateEntryType(mixed $element): bool
    {
        if (! $element instanceof Entry) {
            return $this->operator === 'isNot';
        }

        $typeHandle = $element->getType()->handle;

        return match ($this->operator) {
            'is' => $typeHandle === $this->value,
            'isNot' => $typeHandle !== $this->value,
            default => true,
        };
    }

    private static function isConsole(): bool
    {
        return app()->runningInConsole();
    }
}
