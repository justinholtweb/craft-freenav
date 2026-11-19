<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Events;

use JustinHoltWeb\FreeNav\Elements\Node;

/**
 * Fired when a node works out whether it's active (FreeNav 5: `Node::EVENT_NODE_ACTIVE`). Set
 * `$isActive` to decide it yourself; leave it null to let FreeNav compare URLs.
 */
class ResolvingNodeActive
{
    public function __construct(
        public Node $node,
        public ?bool $isActive = null,
    ) {}
}
