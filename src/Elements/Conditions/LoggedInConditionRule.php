<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Elements\Conditions;

use CraftCms\Cms\Condition\BaseLightswitchConditionRule;

use function CraftCms\Cms\t;

class LoggedInConditionRule extends BaseLightswitchConditionRule
{
    public function getLabel(): string
    {
        return t('Logged In', category: 'free-nav');
    }
}
