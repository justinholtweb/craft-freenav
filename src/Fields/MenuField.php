<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Fields;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Field\Contracts\PreviewableFieldInterface;
use CraftCms\Cms\Field\Field;
use CraftCms\Cms\Field\FieldContext;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Query;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\Choice;
use JustinHoltWeb\FreeNav\Services\Menus;
use Override;

use function CraftCms\Cms\t;

/**
 * Lets editors pick a menu. The value is the menu's handle, so templates can do
 * `craft.freenav.render(entry.myMenuField)`.
 */
class MenuField extends Field implements PreviewableFieldInterface
{
    #[Override]
    public static function displayName(): string
    {
        return t('FreeNav Menu', category: 'free-nav');
    }

    #[Override]
    public static function icon(): string
    {
        return 'list';
    }

    #[Override]
    public static function phpType(): string
    {
        return 'string|null';
    }

    #[Override]
    public static function dbType(): string
    {
        return Query::TYPE_STRING;
    }

    #[Override]
    public function uiControl(FieldContext $context): Control
    {
        $options = [];

        foreach (app(Menus::class)->getAllMenus() as $menu) {
            $options[] = ['label' => (string) $menu->name, 'value' => (string) $menu->handle];
        }

        return Choice::make($context->path)
            ->options($options)
            ->placeholder(t('Select a menu…', category: 'free-nav'))
            ->value($this->serializeValue($context->value, $context->element));
    }

    #[Override]
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    #[Override]
    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    #[Override]
    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        $menu = app(Menus::class)->getMenuByHandle($value);

        return Html::encode($menu ? (string) $menu->name : $value);
    }

    #[Override]
    public function previewPlaceholderHtml(mixed $value, ?ElementInterface $element): string
    {
        return Html::encode(is_string($value) && $value !== '' ? $value : t('Main menu', category: 'free-nav'));
    }
}
