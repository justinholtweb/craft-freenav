<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\TextField;
use Override;

use function CraftCms\Cms\t;

class BadgeField extends TextField
{
    public string $attribute = 'badge';

    public bool $requirable = false;

    public ?string $placeholder = 'New';

    #[Override]
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Badge Text', category: 'free-nav');
    }

    #[Override]
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Optional badge text displayed next to the node title (e.g., "New", "Sale").', category: 'free-nav');
    }
}
