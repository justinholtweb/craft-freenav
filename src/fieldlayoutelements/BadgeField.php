<?php

namespace justinholt\freenav\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use justinholt\freenav\elements\Node;

class BadgeField extends BaseNativeField
{
    public string $attribute = 'badge';
    public bool $requirable = false;

    public function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'Badge Text');
    }

    public function instructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'Optional badge text displayed next to the node title (e.g., "New", "Sale").');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Node|null $element */
        return Craft::$app->getView()->renderTemplate('_includes/forms/text', [
            'id' => 'badge',
            'name' => 'badge',
            'value' => $element?->badge ?? '',
            'placeholder' => 'New',
            'size' => 20,
        ]);
    }
}
