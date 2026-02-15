<?php

namespace justinholt\freenav\events;

use justinholt\freenav\elements\Node;
use yii\base\Event;

class NodeEvent extends Event
{
    public ?Node $node = null;
    public bool $isNew = false;
}
