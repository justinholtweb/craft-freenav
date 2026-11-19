<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Events;

use JustinHoltWeb\FreeNav\Enums\NodeType;

/**
 * Fired when the element types nodes can link to are listed (FreeNav 5:
 * `NodeTypes::EVENT_REGISTER_LINKABLE_ELEMENTS`). Keyed by node type value.
 */
class RegisterLinkableElements
{
    /**
     * @param  array<string, array{nodeType: NodeType, elementType: class-string, label: string}>  $elementTypes
     */
    public function __construct(
        public array $elementTypes,
    ) {}
}
