<?php

namespace justinholt\freenav\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Json;
use justinholt\freenav\elements\Node;

class VisibilityRulesField extends BaseNativeField
{
    public string $attribute = 'visibilityRules';
    public bool $requirable = false;

    public function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'Visibility Rules');
    }

    public function instructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('free-nav', 'JSON rules controlling when this node is visible. Types: userGroup, loggedIn, urlSegment, entryType.');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Node|null $element */
        $value = $element?->getVisibilityRulesArray() ?? [];

        return Craft::$app->getView()->renderTemplate('_includes/forms/textarea', [
            'id' => 'visibilityRules',
            'name' => 'visibilityRules',
            'value' => $value ? Json::encode($value, JSON_PRETTY_PRINT) : '',
            'class' => 'code',
            'rows' => 4,
            'placeholder' => '[{"type":"loggedIn","operator":"is","value":true}]',
        ]);
    }
}
