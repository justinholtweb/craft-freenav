<?php

namespace justinholt\freenav\events;

use yii\base\Event;

class RegisterNodeTypeEvent extends Event
{
    public array $types = [];
}
