<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Enums;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Entry\Elements\Entry;

enum NodeType: string
{
    case Entry = 'entry';

    /**
     * Craft 6 has no categories. The case stays so a menu saved by FreeNav 5 can still be read; the
     * upgrade migration turns category nodes into entry nodes (Craft's entrify keeps element IDs) or,
     * failing that, into custom URL nodes. It is never offered for new nodes.
     */
    case Category = 'category';

    case Asset = 'asset';
    case Product = 'product';
    case Custom = 'custom';
    case Passive = 'passive';
    case Site = 'site';

    /**
     * TODO(commerce-6): Commerce 6 isn't installable yet, so this follows Craft 6's
     * `<Domain>\Elements\<Element>` layout and is a guess. Until it's confirmed, `class_exists()`
     * fails and product nodes simply aren't offered. It was `craft\commerce\elements\Product`.
     */
    public const string COMMERCE_PRODUCT_CLASS = 'CraftCms\\Commerce\\Product\\Elements\\Product';

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
        return $this !== self::Passive;
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

    /**
     * Whether new nodes of this type can be created on this install.
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::Category => false,
            self::Product => $this->elementType() !== null,
            default => true,
        };
    }

    /**
     * @return class-string<ElementInterface>|null
     */
    public function elementType(): ?string
    {
        return match ($this) {
            self::Entry => Entry::class,
            self::Asset => Asset::class,
            self::Product => class_exists(self::COMMERCE_PRODUCT_CLASS) ? self::COMMERCE_PRODUCT_CLASS : null,
            default => null,
        };
    }
}
