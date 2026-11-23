<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\IconPicker;
use CraftCms\Cms\Ui\Enums\ControlMode;
use Override;

use function CraftCms\Cms\t;

/**
 * Craft's icon picker, limited to Font Awesome Free: Craft's Pro icons are licensed for its control
 * panel, and this icon is printed on the site. `NodeRules` holds the same line on save.
 */
class CraftIconField extends BaseNativeField
{
    public string $attribute = 'craftIcon';

    public bool $requirable = false;

    #[Override]
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Icon', category: 'free-nav');
    }

    #[Override]
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Printed as inline SVG before the title. Takes the place of the icon class when both are set.', category: 'free-nav');
    }

    #[Override]
    protected function uiControl(FieldLayoutElementContext $context): ?Control
    {
        $value = $this->value($context->element);

        return IconPicker::make($this->attribute())
            ->freeOnly()
            ->value(is_string($value) && $value !== '' ? $value : null)
            ->mode($context->mode === ControlMode::Editable ? ControlMode::Editable : ControlMode::ReadOnly);
    }
}
