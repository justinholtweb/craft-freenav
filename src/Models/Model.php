<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Models;

use CraftCms\Cms\Component\Component;

/**
 * Base for FreeNav's models.
 *
 * Craft 6 validates components with Laravel rules (`getRules()`). Checks that don't fit a rule
 * string live in `checks()`, which runs after the rules on every full `validate()`.
 */
abstract class Model extends Component
{
    public function validate(array|string|null $attributeNames = null, bool $clearErrors = true, bool $throw = false): bool
    {
        parent::validate($attributeNames, $clearErrors);

        if ($attributeNames === null) {
            $this->checks();
        }

        return $this->errors()->isEmpty();
    }

    public function addError(string $attribute, string $message): void
    {
        $this->errors()->add($attribute, $message);
    }

    public function hasErrors(?string $attribute = null): bool
    {
        return $attribute === null ? $this->errors()->isNotEmpty() : $this->errors()->has($attribute);
    }

    /**
     * Cross-field validation, run after the rules. Report problems with `addError()`.
     */
    protected function checks(): void {}
}
