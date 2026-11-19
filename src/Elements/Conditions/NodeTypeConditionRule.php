<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Elements\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use Illuminate\Database\Query\Builder;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Elements\Queries\NodeQuery;
use JustinHoltWeb\FreeNav\Enums\NodeType;

use function CraftCms\Cms\t;

class NodeTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Node Type', category: 'free-nav');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['nodeType'];
    }

    protected function options(): array
    {
        $options = [];

        foreach (NodeType::cases() as $type) {
            $options[] = ['label' => $type->label(), 'value' => $type->value];
        }

        return $options;
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        NodeQuery::applyNodeType($query, $this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        return $element instanceof Node && $this->matchValue($element->nodeType);
    }
}
