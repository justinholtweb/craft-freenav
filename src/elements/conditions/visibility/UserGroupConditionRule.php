<?php

namespace justinholt\freenav\elements\conditions\visibility;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;

class UserGroupConditionRule extends BaseMultiSelectConditionRule implements VisibilityRuleInterface
{
    use MultiSelectRuleTrait;

    public function getLabel(): string
    {
        return Craft::t('free-nav', 'User Group');
    }

    public static function ruleType(): string
    {
        return 'userGroup';
    }

    protected function options(): array
    {
        $options = [['value' => 'guests', 'label' => Craft::t('free-nav', 'Guests (not logged in)')]];

        foreach (Craft::$app->getUserGroups()->getAllGroups() as $group) {
            $options[] = ['value' => $group->handle, 'label' => Craft::t('site', $group->name)];
        }

        return $options;
    }
}
