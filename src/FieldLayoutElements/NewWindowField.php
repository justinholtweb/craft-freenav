<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Enums\ControlMode;
use Override;

use function CraftCms\Cms\t;

class NewWindowField extends BaseNativeField
{
    public string $attribute = 'newWindow';

    public bool $requirable = false;

    #[Override]
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Open in New Window', category: 'free-nav');
    }

    #[Override]
    protected function uiControl(FieldLayoutElementContext $context): ?Control
    {
        return Lightswitch::make($this->attribute())
            ->value((bool) $this->value($context->element))
            ->mode($context->mode === ControlMode::Editable ? ControlMode::Editable : ControlMode::ReadOnly);
    }
}
