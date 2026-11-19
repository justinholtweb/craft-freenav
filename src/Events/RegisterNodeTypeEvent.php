<?php

namespace JustinHoltWeb\FreeNav\Events;

use yii\base\Event;

class RegisterNodeTypeEvent extends Event
{
    public array $types = [];
}
