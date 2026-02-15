<?php

namespace justinholt\freenav\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use justinholt\freenav\elements\Node;

class NewWindowField extends BaseNativeField
{
    public string $attribute = 'newWindow';
    public bool $requirable = false;

    public function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'Open in New Window');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Node|null $element */
        return Craft::$app->getView()->renderTemplate('_includes/forms/lightswitch', [
            'id' => 'newWindow',
            'name' => 'newWindow',
            'on' => $element?->newWindow ?? false,
        ]);
    }
}
