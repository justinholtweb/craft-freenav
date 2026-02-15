<?php

namespace justinholt\freenav\elements\conditions;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use justinholt\freenav\elements\db\NodeQuery;
use justinholt\freenav\elements\Node;
use justinholt\freenav\enums\NodeType;

class NodeTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('free-nav', 'Node Type');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['nodeType'];
    }

    protected function options(): array
    {
        $options = [];

        foreach (NodeType::cases() as $type) {
            $options[] = [
                'label' => $type->label(),
                'value' => $type->value,
            ];
        }

        return $options;
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var NodeQuery $query */
        $query->nodeType($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Node $element */
        return $this->matchValue($element->nodeType);
    }
}
