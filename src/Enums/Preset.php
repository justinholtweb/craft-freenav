<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Enums;

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
        return '_'.$this->value;
    }

    /**
     * The site template the preset renders, under FreeNav's `_freenav` template root. A project's
     * own `templates/_freenav/_presets/_<name>.twig` is found first, so a preset can be overridden.
     */
    public function templatePath(): string
    {
        return '_freenav/_presets/'.$this->templateName();
    }
}
