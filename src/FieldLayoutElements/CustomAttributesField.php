<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\TextareaField;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\Textarea;
use Override;

use function CraftCms\Cms\t;

/**
 * Edited as JSON text; `Node::setAttributesFromRequest()` decodes it and `NodeRules` checks it.
 */
class CustomAttributesField extends TextareaField
{
    public string $attribute = 'customAttributes';

    public bool $requirable = false;

    public ?int $rows = 4;

    public ?string $placeholder = '[{"key":"data-track","value":"nav"}]';

    #[Override]
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Custom Attributes', category: 'free-nav');
    }

    #[Override]
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Custom HTML attributes as a JSON array of {key, value} objects. Allowed names: data-*, aria-*, title, rel, id, lang, role, itemprop and similar plain attributes.', category: 'free-nav');
    }

    #[Override]
    protected function value(?ElementInterface $element = null): mixed
    {
        $value = $element?->{$this->attribute()};

        if (is_string($value)) {
            $decoded = Json::decodeIfJson($value);

            // Text that isn't JSON (a failed save) is shown back as typed.
            if (! is_array($decoded)) {
                return $value;
            }

            $value = $decoded;
        }

        return is_array($value) && $value !== [] ? (string) Json::encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    }

    #[Override]
    protected function uiControl(FieldLayoutElementContext $context): ?Control
    {
        $control = parent::uiControl($context);

        return $control instanceof Textarea ? $control->monospace() : $control;
    }
}
