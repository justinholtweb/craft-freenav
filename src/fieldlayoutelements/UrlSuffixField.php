<?php

namespace justinholt\freenav\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use justinholt\freenav\elements\Node;

class UrlSuffixField extends BaseNativeField
{
    public string $attribute = 'urlSuffix';
    public bool $requirable = false;

    public function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'URL Suffix');
    }

    public function instructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'Appended to the URL (e.g., "#section" or "?ref=nav").');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Node|null $element */
        return Craft::$app->getView()->renderTemplate('_includes/forms/text', [
            'id' => 'urlSuffix',
            'name' => 'urlSuffix',
            'value' => $element?->urlSuffix ?? '',
            'placeholder' => '#section',
            'class' => 'code',
        ]);
    }
}
