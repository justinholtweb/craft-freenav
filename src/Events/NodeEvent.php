<?php

namespace JustinHoltWeb\FreeNav\Events;

use JustinHoltWeb\FreeNav\Elements\Node;
use yii\base\Event;

class NodeEvent extends Event
{
    public ?Node $node = null;
    public bool $isNew = false;
}
