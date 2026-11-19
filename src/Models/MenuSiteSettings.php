<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Models;

class MenuSiteSettings extends Model
{
    public ?int $id = null;

    public ?int $menuId = null;

    public ?int $siteId = null;

    public bool $enabled = true;

    public mixed $dateCreated = null;

    public mixed $dateUpdated = null;

    public ?string $uid = null;

    public function getRules(): array
    {
        return [
            'siteId' => ['required', 'integer'],
            'enabled' => ['boolean'],
        ];
    }
}
