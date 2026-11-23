<?php

namespace justinholt\freenav\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use justinholt\freenav\elements\conditions\visibility\NodeVisibilityCondition;
use justinholt\freenav\elements\Node;

/**
 * Edits the node's visibility rules with Craft's condition builder. `Node::setVisibilityRulesFromPost()`
 * turns what it posts back into the stored rules.
 */
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
        return Craft::t('free-nav', 'Only show this node when every rule matches. With no rules, it always shows.');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Node|null $element */
        $condition = NodeVisibilityCondition::fromRules($element?->getVisibilityRulesArray() ?? []);
        $condition->id = 'visibilityRules';
        $condition->name = 'visibilityRules';

        return $condition->getBuilderHtml();
    }
}
