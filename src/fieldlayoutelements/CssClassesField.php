<?php

namespace justinholt\freenav\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use justinholt\freenav\elements\Node;

class CssClassesField extends BaseNativeField
{
    public string $attribute = 'classes';
    public bool $requirable = false;

    public function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'CSS Classes');
    }

    public function instructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'Additional CSS classes for this node.');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Node|null $element */
        return Craft::$app->getView()->renderTemplate('_includes/forms/text', [
            'id' => 'classes',
            'name' => 'classes',
            'value' => $element?->classes ?? '',
            'class' => 'code',
        ]);
    }
}
