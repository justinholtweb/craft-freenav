<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\TextField;
use Override;

use function CraftCms\Cms\t;

class CssClassesField extends TextField
{
    public string $attribute = 'classes';

    public bool $requirable = false;

    public ?string $placeholder = null;

    #[Override]
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('CSS Classes', category: 'free-nav');
    }

    #[Override]
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Additional CSS classes for this node.', category: 'free-nav');
    }
}
