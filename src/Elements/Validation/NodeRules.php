<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Elements\Validation;

use Closure;
use CraftCms\Cms\Element\Validation\ElementRules;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Enums\NodeType;
use JustinHoltWeb\FreeNav\Models\VisibilityRule;
use Override;

use function CraftCms\Cms\t;

/**
 * Validation for nodes. The two node settings that reach the front end unescaped-in-meaning — the
 * custom URL and the custom attributes — are checked here, so every save path (the node editor,
 * the add-node form, imports, the console) goes through the same rules.
 *
 * @extends ElementRules<Node>
 *
 * @property Node $subject
 */
class NodeRules extends ElementRules
{
    /** @return array<string, mixed> */
    #[Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'menuId' => ['required', 'integer'],
            'nodeType' => ['required', 'string'],
            'customUrl' => ['nullable', 'string', $this->customUrlRule(...)],
            'classes' => ['nullable', 'string', 'max:255'],
            'urlSuffix' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:100'],
            'newWindow' => ['boolean'],
            'customAttributes' => ['nullable', $this->customAttributesRule(...)],
            'visibilityRules' => ['nullable', $this->visibilityRulesRule(...)],
            'data' => ['nullable', $this->jsonRule(...)],
        ];
    }

    private function customUrlRule(string $attribute, mixed $value, Closure $fail): void
    {
        if (
            is_string($value) && trim($value) !== ''
            && $this->subject->getNodeType() === NodeType::Custom
            && Node::resolveCustomUrl($value) === null
        ) {
            $fail(t('Use a web address, a path, an anchor, or a mailto:, tel: or sms: link. Environment variables and aliases must resolve to an http(s) address.', category: 'free-nav'));
        }
    }

    private function customAttributesRule(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->isJsonList($value)) {
            $fail(t('Custom attributes must be a JSON array of {key, value} objects.', category: 'free-nav'));

            return;
        }

        foreach ($this->subject->getCustomAttributesArray() as $attr) {
            $key = trim((string) (is_array($attr) ? ($attr['key'] ?? '') : ''));

            if ($key !== '' && ! Node::isSafeAttributeName($key)) {
                $fail(t('“{key}” can’t be used as a custom attribute. Use data-*, aria-*, title, rel, id, lang, role or another plain attribute; event handlers, URL attributes, style and framework directives such as x-* or hx-* aren’t allowed.', ['key' => $key], category: 'free-nav'));
            } elseif ($key !== '' && is_array($attr) && Node::customAttributeValue($attr['value'] ?? null) === null) {
                $fail(t('The value of “{key}” must be text.', ['key' => $key], category: 'free-nav'));
            }
        }
    }

    private function visibilityRulesRule(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->isJsonList($value)) {
            $fail(t('Visibility rules must be a JSON array of {type, operator, value} objects.', category: 'free-nav'));

            return;
        }

        foreach ($this->subject->getVisibilityRulesArray() as $rule) {
            if (! is_array($rule)) {
                $fail(t('Visibility rules must be a JSON array of {type, operator, value} objects.', category: 'free-nav'));

                return;
            }

            foreach (VisibilityRule::fromArray($rule)->problems() as $problem) {
                $fail($problem);
            }
        }
    }

    private function jsonRule(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! is_array(json_decode($value, true))) {
            $fail(t('This must be valid JSON.', category: 'free-nav'));
        }
    }

    /**
     * An array, or a string holding a JSON array: what the node stores.
     */
    private function isJsonList(mixed $value): bool
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value);
    }
}
