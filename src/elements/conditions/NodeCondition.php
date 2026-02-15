<?php

namespace justinholt\freenav\elements\conditions;

use craft\elements\conditions\ElementCondition;

class NodeCondition extends ElementCondition
{
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            NodeTypeConditionRule::class,
        ]);
    }
}
