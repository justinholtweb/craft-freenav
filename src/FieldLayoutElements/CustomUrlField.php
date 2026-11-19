<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\TextField;
use JustinHoltWeb\FreeNav\Elements\Node;
use Override;

use function CraftCms\Cms\t;

/**
 * The URL of a custom or site node. Always in a node layout (it's mandatory), and only shown for
 * the node types that use it: an entry or asset node gets its URL from what it links to.
 */
class CustomUrlField extends TextField
{
    public string $attribute = 'customUrl';

    public bool $mandatory = true;

    public bool $requirable = false;

    public ?string $placeholder = 'https://';

    public bool $autocorrect = false;

    public bool $autocapitalize = false;

    #[Override]
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('URL', category: 'free-nav');
    }

    #[Override]
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('A web address, a path, an anchor, or a mailto:, tel: or sms: link. Environment variables and aliases must resolve to an http(s) address.', category: 'free-nav');
    }

    #[Override]
    public function showInForm(?ElementInterface $element = null): bool
    {
        if ($element instanceof Node && ! in_array($element->nodeType, ['custom', 'site'], true)) {
            return false;
        }

        return parent::showInForm($element);
    }
}
