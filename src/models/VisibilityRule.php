<?php

namespace justinholt\freenav\models;

use Craft;
use craft\base\Model;

/**
 * One of a node's visibility rules: `{"type": "loggedIn", "operator": "is", "value": true}`.
 *
 * A node is shown only when every rule passes. `userGroup` and `entryType` take one handle or a
 * list of them: `is` passes when any matches, `isNot` when none does. The control panel edits
 * these through `NodeVisibilityCondition`; this list is still what's stored and exported.
 */
class VisibilityRule extends Model
{
    public string $type = '';
    public string $operator = 'is';
    public mixed $value = null;

    public function defineRules(): array
    {
        return [
            [['type'], 'required'],
            [['type'], 'in', 'range' => ['userGroup', 'loggedIn', 'urlSegment', 'entryType', 'custom']],
            [['operator'], 'in', 'range' => ['is', 'isNot', 'contains', 'startsWith']],
            [['value'], function(string $attribute) {
                if (in_array($this->type, ['userGroup', 'entryType'], true) && $this->getHandles() === []) {
                    $this->addError($attribute, Craft::t('free-nav', 'Choose at least one.'));
                }
            }],
        ];
    }

    /**
     * The handles a `userGroup` or `entryType` rule matches against.
     *
     * @return string[]
     */
    public function getHandles(): array
    {
        $values = is_array($this->value) ? $this->value : [$this->value];

        return array_values(array_filter(
            array_map(fn($v) => is_scalar($v) ? trim((string)$v) : '', $values),
            fn(string $v) => $v !== '',
        ));
    }

    public function evaluate(): bool
    {
        return match ($this->type) {
            'loggedIn' => $this->_evaluateLoggedIn(),
            'userGroup' => $this->_evaluateUserGroup(),
            'urlSegment' => $this->_evaluateUrlSegment(),
            'entryType' => $this->_evaluateEntryType(),
            default => true,
        };
    }

    private function _evaluateLoggedIn(): bool
    {
        $isLoggedIn = !Craft::$app->getUser()->getIsGuest();

        return match ($this->operator) {
            'is' => $isLoggedIn === (bool)$this->value,
            'isNot' => $isLoggedIn !== (bool)$this->value,
            default => true,
        };
    }

    private function _evaluateUserGroup(): bool
    {
        $user = Craft::$app->getUser()->getIdentity();
        $matches = false;

        foreach ($this->getHandles() as $handle) {
            if ($handle === 'guests' ? $user === null : ($user !== null && $user->isInGroup($handle))) {
                $matches = true;
                break;
            }
        }

        return $this->_applyMatch($matches);
    }

    private function _evaluateUrlSegment(): bool
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return true;
        }

        $uri = $request->getPathInfo();

        return match ($this->operator) {
            'is' => $uri === $this->value,
            'isNot' => $uri !== $this->value,
            'contains' => str_contains($uri, (string)$this->value),
            'startsWith' => str_starts_with($uri, (string)$this->value),
            default => true,
        };
    }

    private function _evaluateEntryType(): bool
    {
        $element = Craft::$app->getUrlManager()->getMatchedElement();
        $typeHandle = $element instanceof \craft\elements\Entry ? $element->getType()->handle : null;

        return $this->_applyMatch($typeHandle !== null && in_array($typeHandle, $this->getHandles(), true));
    }

    private function _applyMatch(bool $matches): bool
    {
        return match ($this->operator) {
            'is' => $matches,
            'isNot' => !$matches,
            default => true,
        };
    }
}
