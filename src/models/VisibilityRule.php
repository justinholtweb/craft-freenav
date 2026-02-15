<?php

namespace justinholt\freenav\models;

use Craft;
use craft\base\Model;

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
        ];
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

        $isInGroup = $user->isInGroup($this->value);

        return match ($this->operator) {
            'is' => $isInGroup,
            'isNot' => !$isInGroup,
            default => true,
        };
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

        if (!$element instanceof \craft\elements\Entry) {
            return $this->operator === 'isNot';
        }

        $typeHandle = $element->getType()->handle;

        return match ($this->operator) {
            'is' => $typeHandle === $this->value,
            'isNot' => $typeHandle !== $this->value,
            default => true,
        };
    }
}
