<?php

namespace justinholt\freenav\elements\conditions\visibility;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;

class EntryTypeConditionRule extends BaseMultiSelectConditionRule implements VisibilityRuleInterface
{
    use MultiSelectRuleTrait;

    public function getLabel(): string
    {
        return Craft::t('free-nav', 'Page Entry Type');
    }

    public function getLabelHint(): ?string
    {
        return Craft::t('free-nav', 'The type of the entry the page shows');
    }

    public static function ruleType(): string
    {
        return 'entryType';
    }

    protected function options(): array
    {
        $options = [];

        foreach (Craft::$app->getEntries()->getAllEntryTypes() as $entryType) {
            $options[] = ['value' => $entryType->handle, 'label' => Craft::t('site', $entryType->name)];
        }

        return $options;
    }
}
