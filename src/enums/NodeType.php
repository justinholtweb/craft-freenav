<?php

namespace justinholt\freenav\enums;

use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;

enum NodeType: string
{
    case Entry = 'entry';
    case Category = 'category';
    case Asset = 'asset';
    case Product = 'product';
    case Custom = 'custom';
    case Passive = 'passive';
    case Site = 'site';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entry',
            self::Category => 'Category',
            self::Asset => 'Asset',
            self::Product => 'Product',
            self::Custom => 'Custom URL',
            self::Passive => 'Passive',
            self::Site => 'Site',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Entry => '#e5422b',
            self::Category => '#f5a623',
            self::Asset => '#27AB83',
            self::Product => '#7c3aed',
            self::Custom => '#0d99ff',
            self::Passive => '#8b8b8b',
            self::Site => '#e5422b',
        };
    }

    public function hasUrl(): bool
    {
        return match ($this) {
            self::Passive => false,
            default => true,
        };
    }

    public function hasTitle(): bool
    {
        return true;
    }

    public function isElement(): bool
    {
        return match ($this) {
            self::Entry, self::Category, self::Asset, self::Product => true,
            default => false,
        };
    }

    public function elementType(): ?string
    {
        return match ($this) {
            self::Entry => Entry::class,
            self::Category => Category::class,
            self::Asset => Asset::class,
            self::Product => $this->_commerceProductClass(),
            default => null,
        };
    }

    private function _commerceProductClass(): ?string
    {
        $class = 'craft\\commerce\\elements\\Product';
        return class_exists($class) ? $class : null;
    }
}
