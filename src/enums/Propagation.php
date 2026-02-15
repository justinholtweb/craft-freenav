<?php

namespace justinholt\freenav\enums;

enum Propagation: string
{
    case None = 'none';
    case SiteGroup = 'siteGroup';
    case Language = 'language';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Only save nodes to the site they were created in',
            self::SiteGroup => 'Save nodes to other sites in the same site group',
            self::Language => 'Save nodes to other sites with the same language',
            self::All => 'Save nodes to all sites the menu is enabled for',
        };
    }
}
