<?php

namespace justinholt\freenav\models;

use craft\base\Model;

class Settings extends Model
{
    public bool $cacheEnabled = true;
    public int $cacheDuration = 3600;
    public bool $ariaEnabled = true;
    public string $defaultPreset = 'default';
    public string $activeClass = 'active';
    public string $hasChildrenClass = 'has-children';
    public bool $restApiEnabled = true;

    public function defineRules(): array
    {
        return [
            [['cacheDuration'], 'integer', 'min' => 0],
            [['defaultPreset'], 'in', 'range' => ['default', 'dropdown', 'sidebar', 'breadcrumb', 'footer', 'mega']],
            [['activeClass', 'hasChildrenClass'], 'string', 'max' => 100],
        ];
    }
}
