<?php

namespace justinholt\freenav\events;

use yii\base\Event;

class RegisterLinkableElementEvent extends Event
{
    public array $elementTypes = [];
}
