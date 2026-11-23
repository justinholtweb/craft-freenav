<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\ConditionBuilder;
use CraftCms\Cms\Ui\Enums\ControlMode;
use JustinHoltWeb\FreeNav\Elements\Conditions\NodeVisibilityCondition;
use JustinHoltWeb\FreeNav\Elements\Node;
use Override;

use function CraftCms\Cms\t;

/**
 * Edited with Craft's condition builder. The node keeps its rules as `[{type, operator, value}]`:
 * the builder is built from them, and `Node::setAttributesFromRequest()` turns what it posts back
 * into them ({@see NodeVisibilityCondition}).
 */
class VisibilityRulesField extends BaseNativeField
{
    public string $attribute = 'visibilityRules';

    public bool $requirable = false;

    #[Override]
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Visibility Rules', category: 'free-nav');
    }

    #[Override]
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Show this node only when every rule passes. With no rules it’s always shown.', category: 'free-nav');
    }

    #[Override]
    protected function uiControl(FieldLayoutElementContext $context): ?Control
    {
        $element = $context->element instanceof Node ? $context->element : null;
        $config = $element->visibilityRulesBuilderConfig
            ?? NodeVisibilityCondition::configFromRules($element?->getVisibilityRulesArray() ?? []);

        return ConditionBuilder::make($this->attribute())
            ->conditionClass(NodeVisibilityCondition::class)
            ->addRuleLabel(t('Add a rule', category: 'free-nav'))
            ->value($config)
            ->mode($context->mode === ControlMode::Editable ? ControlMode::Editable : ControlMode::ReadOnly);
    }
}
