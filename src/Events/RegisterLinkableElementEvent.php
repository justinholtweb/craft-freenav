<?php

namespace JustinHoltWeb\FreeNav\Events;

use yii\base\Event;

class RegisterLinkableElementEvent extends Event
{
    public array $elementTypes = [];
}
