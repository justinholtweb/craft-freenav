<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Elements\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Support\Facades\UserGroups;

use function CraftCms\Cms\t;

/**
 * Groups are offered by handle — what a node's stored rule holds — with “Guests” for visitors who
 * aren't logged in.
 */
class UserGroupConditionRule extends BaseMultiSelectConditionRule
{
    public function getLabel(): string
    {
        return t('User Group', category: 'free-nav');
    }

    protected function options(): array
    {
        $options = [['value' => 'guests', 'label' => t('Guests', category: 'free-nav')]];

        foreach (UserGroups::getAllGroups() as $group) {
            $options[] = ['value' => (string) $group->handle, 'label' => (string) $group->name];
        }

        return $options;
    }
}
