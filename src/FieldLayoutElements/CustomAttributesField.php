<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\Table;
use CraftCms\Cms\Ui\Enums\ControlMode;
use JustinHoltWeb\FreeNav\Elements\Node;
use Override;

use function CraftCms\Cms\t;

/**
 * A Key/Value table. The node stores the rows as `[{key, value}]`; `Node::setAttributesFromRequest()`
 * drops rows without a key and `NodeRules` checks the names against the allowlist.
 */
class CustomAttributesField extends BaseNativeField
{
    public string $attribute = 'customAttributes';

    public bool $requirable = false;

    #[Override]
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Custom Attributes', category: 'free-nav');
    }

    #[Override]
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('HTML attributes for the link. Allowed names: data-*, aria-*, title, rel, id, lang, role, itemprop and similar plain attributes.', category: 'free-nav');
    }

    #[Override]
    protected function uiControl(FieldLayoutElementContext $context): ?Control
    {
        $rows = [];

        foreach ($context->element instanceof Node ? $context->element->getCustomAttributesArray() : [] as $attr) {
            if (is_array($attr)) {
                $rows[] = [
                    'key' => is_scalar($attr['key'] ?? null) ? (string) $attr['key'] : '',
                    'value' => is_scalar($attr['value'] ?? null) ? (string) $attr['value'] : '',
                ];
            }
        }

        return Table::make($this->attribute())
            ->columns([
                'key' => ['heading' => t('Key', category: 'free-nav'), 'type' => 'singleline', 'code' => true],
                'value' => ['heading' => t('Value', category: 'free-nav'), 'type' => 'singleline'],
            ])
            ->addRowLabel(t('Add an attribute', category: 'free-nav'))
            ->allowAdd()
            ->allowDelete()
            ->allowReorder()
            ->value($rows)
            ->mode($context->mode === ControlMode::Editable ? ControlMode::Editable : ControlMode::ReadOnly);
    }
}
