<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Elements\Conditions;

use CraftCms\Cms\Element\Conditions\ElementCondition;
use Override;

class NodeCondition extends ElementCondition
{
    #[Override]
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            NodeTypeConditionRule::class,
        ]);
    }
}
