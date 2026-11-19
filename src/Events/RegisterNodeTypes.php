<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Events;

use JustinHoltWeb\FreeNav\Enums\NodeType;

/**
 * Fired when the node types available for new nodes are listed (FreeNav 5:
 * `NodeTypes::EVENT_REGISTER_NODE_TYPES`). Remove cases from `$types` to hide them.
 */
class RegisterNodeTypes
{
    /**
     * @param  NodeType[]  $types
     */
    public function __construct(
        public array $types,
    ) {}
}
