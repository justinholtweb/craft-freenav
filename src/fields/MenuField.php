<?php

namespace justinholt\freenav\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use justinholt\freenav\FreeNav;

class MenuField extends Field implements PreviewableFieldInterface
{
    public static function displayName(): string
    {
        return Craft::t('free-nav', 'FreeNav Menu');
    }

    public static function icon(): string
    {
        return 'list';
    }

    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        $menus = FreeNav::getInstance()->getMenus()->getAllMenus();

        $options = [
            ['label' => Craft::t('free-nav', 'Select a menu…'), 'value' => ''],
        ];

        foreach ($menus as $menu) {
            $options[] = [
                'label' => $menu->name,
                'value' => $menu->handle,
            ];
        }

        return Craft::$app->getView()->renderTemplate('free-nav/_components/fieldtypes/Menu/input', [
            'name' => $this->handle,
            'value' => $value,
            'options' => $options,
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('free-nav/_components/fieldtypes/Menu/settings', [
            'field' => $this,
        ]);
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        return $value ?: null;
    }

    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        return $value ?: null;
    }

    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        if (!$value) {
            return '';
        }

        $menu = FreeNav::getInstance()->getMenus()->getMenuByHandle($value);

        return $menu ? $menu->name : $value;
    }

    public function getContentColumnType(): string
    {
        return 'string';
    }
}
