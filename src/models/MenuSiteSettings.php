<?php

namespace justinholt\freenav\models;

use craft\base\Model;

class MenuSiteSettings extends Model
{
    public ?int $id = null;
    public ?int $menuId = null;
    public ?int $siteId = null;
    public bool $enabled = true;

    public function defineRules(): array
    {
        return [
            [['menuId', 'siteId'], 'required'],
            [['enabled'], 'boolean'],
        ];
    }
}
