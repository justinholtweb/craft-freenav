<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Elements\Conditions;

use CraftCms\Cms\Condition\BaseTextConditionRule;
use Override;

use function CraftCms\Cms\t;

/**
 * The path of the page being viewed, without its leading slash (`about/team`).
 */
class UrlPathConditionRule extends BaseTextConditionRule
{
    public function getLabel(): string
    {
        return t('URL Path', category: 'free-nav');
    }

    #[Override]
    protected function operators(): array
    {
        return [
            self::OPERATOR_EQ,
            self::OPERATOR_NE,
            self::OPERATOR_CONTAINS,
            self::OPERATOR_BEGINS_WITH,
        ];
    }
}
