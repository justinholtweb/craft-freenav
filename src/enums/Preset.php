<?php

namespace justinholt\freenav\enums;

enum Preset: string
{
    case Default = 'default';
    case Dropdown = 'dropdown';
    case Sidebar = 'sidebar';
    case Breadcrumb = 'breadcrumb';
    case Footer = 'footer';
    case Mega = 'mega';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Dropdown => 'Dropdown',
            self::Sidebar => 'Sidebar',
            self::Breadcrumb => 'Breadcrumb',
            self::Footer => 'Footer',
            self::Mega => 'Mega Menu',
        };
    }

    public function templateName(): string
    {
        return '_' . $this->value;
    }
}
