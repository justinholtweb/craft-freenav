<?php

namespace justinholt\freenav\events;

use justinholt\freenav\models\Menu;
use yii\base\Event;

class MenuEvent extends Event
{
    public ?Menu $menu = null;
    public bool $isNew = false;
}
