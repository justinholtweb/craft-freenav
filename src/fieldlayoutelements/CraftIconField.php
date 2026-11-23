<?php

namespace justinholt\freenav\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use justinholt\freenav\elements\Node;

/**
 * Craft's icon picker, limited to the free Font Awesome icons (`Node::isAllowedCraftIcon()`).
 */
class CraftIconField extends BaseNativeField
{
    public string $attribute = 'craftIcon';
    public bool $requirable = false;

    public function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'Icon');
    }

    public function instructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'Printed as an inline SVG before the title. Takes the place of the icon class when both are set.');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Node|null $element */
        return Cp::iconPickerHtml([
            'id' => 'craftIcon',
            'name' => 'craftIcon',
            'value' => $element?->craftIcon,
            'freeOnly' => true,
            'static' => $static,
        ]);
    }
}
