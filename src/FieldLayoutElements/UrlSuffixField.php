<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\TextField;
use Override;

use function CraftCms\Cms\t;

class UrlSuffixField extends TextField
{
    public string $attribute = 'urlSuffix';

    public bool $requirable = false;

    public ?string $placeholder = '#section';

    #[Override]
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('URL Suffix', category: 'free-nav');
    }

    #[Override]
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Appended to the URL (e.g., "#section" or "?ref=nav").', category: 'free-nav');
    }
}
