<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Elements\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Support\Facades\EntryTypes;

use function CraftCms\Cms\t;

/**
 * The entry type of the entry the visitor is looking at. Entry types are offered by handle, which
 * is what a node's stored rule holds.
 */
class EntryTypeConditionRule extends BaseMultiSelectConditionRule
{
    public function getLabel(): string
    {
        return t('Page Entry Type', category: 'free-nav');
    }

    protected function options(): array
    {
        $options = [];

        foreach (EntryTypes::getAllEntryTypes() as $entryType) {
            $options[] = ['value' => (string) $entryType->handle, 'label' => (string) $entryType->name];
        }

        return $options;
    }
}
