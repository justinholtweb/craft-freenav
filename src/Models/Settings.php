<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Models;

use CraftCms\Cms\Plugin\PluginSettings;
use Illuminate\Validation\Rule;
use JustinHoltWeb\FreeNav\Enums\Preset;

/**
 * Plugin settings. They stay in project config under `plugins.free-nav.settings`, the same keys
 * as FreeNav 5, so an upgrade keeps them.
 *
 * Text settings are nullable: Laravel turns a blank field into null before Craft assigns it.
 */
class Settings extends PluginSettings
{
    public bool $cacheEnabled = true;

    public int $cacheDuration = 3600;

    public bool $ariaEnabled = true;

    public ?string $defaultPreset = 'default';

    public ?string $activeClass = 'active';

    public ?string $hasChildrenClass = 'has-children';

    public bool $restApiEnabled = true;

    public function getRules(): array
    {
        return [
            'cacheEnabled' => ['boolean'],
            'cacheDuration' => ['integer', 'min:0'],
            'ariaEnabled' => ['boolean'],
            'defaultPreset' => ['nullable', Rule::in(array_column(Preset::cases(), 'value'))],
            'activeClass' => ['nullable', 'string', 'max:100'],
            'hasChildrenClass' => ['nullable', 'string', 'max:100'],
            'restApiEnabled' => ['boolean'],
        ];
    }
}
