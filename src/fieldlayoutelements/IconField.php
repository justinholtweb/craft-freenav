<?php

namespace justinholt\freenav\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use justinholt\freenav\elements\Node;

class IconField extends BaseNativeField
{
    public string $attribute = 'icon';
    public bool $requirable = false;

    public function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'Icon Class');
    }

    public function instructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'CSS icon class (e.g., "fa-home", "icon-menu").');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Node|null $element */
        return Craft::$app->getView()->renderTemplate('_includes/forms/text', [
            'id' => 'icon',
            'name' => 'icon',
            'value' => $element?->icon ?? '',
            'placeholder' => 'fa-home',
        ]);
    }
}
