<?php

namespace JustinHoltWeb\FreeNav\Events;

use JustinHoltWeb\FreeNav\Models\Menu;
use yii\base\Event;

class MenuEvent extends Event
{
    public ?Menu $menu = null;
    public bool $isNew = false;
}
