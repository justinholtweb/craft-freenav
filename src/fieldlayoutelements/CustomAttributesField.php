<?php

namespace justinholt\freenav\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use justinholt\freenav\elements\Node;

/**
 * A Key / Value table. `Node::normalizeCustomAttributes()` turns its rows into the stored
 * `[{key, value}]` list.
 */
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
        return Craft::t('free-nav', 'HTML attributes added to the link. Allowed names: `data-*`, `aria-*`, `title`, `rel`, `id`, `lang`, `role`, `itemprop` and similar plain attributes.');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Node|null $element */
        $rows = [];

        foreach ($element?->getCustomAttributesArray() ?? [] as $attr) {
            if (is_array($attr)) {
                $rows[] = [
                    'key' => (string)($attr['key'] ?? ''),
                    'value' => is_scalar($attr['value'] ?? null) ? (string)$attr['value'] : '',
                ];
            }
        }

        return Craft::$app->getView()->renderTemplate('_includes/forms/editableTable', [
            'id' => 'customAttributes',
            'name' => 'customAttributes',
            'cols' => [
                'key' => [
                    'type' => 'singleline',
                    'heading' => Craft::t('free-nav', 'Key'),
                    'placeholder' => 'data-track',
                    'code' => true,
                ],
                'value' => [
                    'type' => 'singleline',
                    'heading' => Craft::t('free-nav', 'Value'),
                ],
            ],
            'rows' => $rows,
            'allowAdd' => true,
            'allowDelete' => true,
            'allowReorder' => true,
            'addRowLabel' => Craft::t('free-nav', 'Add an attribute'),
            'static' => $static,
        ]);
    }
}
