<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\TextField;
use Override;

use function CraftCms\Cms\t;

class IconField extends TextField
{
    public string $attribute = 'icon';

    public bool $requirable = false;

    public ?string $placeholder = 'fa-home';

    #[Override]
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Icon Class', category: 'free-nav');
    }

    #[Override]
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('CSS icon class (e.g., "fa-home", "icon-menu").', category: 'free-nav');
    }
}
