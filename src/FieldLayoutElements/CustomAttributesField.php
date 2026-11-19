<?php

namespace JustinHoltWeb\FreeNav\FieldLayoutElements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Json;
use JustinHoltWeb\FreeNav\Elements\Node;

class CustomAttributesField extends BaseNativeField
{
    public string $attribute = 'customAttributes';
    public bool $requirable = false;

    public function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'Custom Attributes');
    }

    public function instructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'Custom HTML attributes as JSON array of {key, value} objects.');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Node|null $element */
        $value = $element?->getCustomAttributesArray() ?? [];

        return Craft::$app->getView()->renderTemplate('_includes/forms/textarea', [
            'id' => 'customAttributes',
            'name' => 'customAttributes',
            'value' => $value ? Json::encode($value, JSON_PRETTY_PRINT) : '',
            'class' => 'code',
            'rows' => 4,
        ]);
    }
}
