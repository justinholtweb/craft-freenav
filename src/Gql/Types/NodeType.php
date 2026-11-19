<?php

namespace JustinHoltWeb\FreeNav\Gql\Types;

use craft\gql\base\ObjectType;
use JustinHoltWeb\FreeNav\Gql\Interfaces\NodeInterface;

class NodeType extends ObjectType
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            NodeInterface::getType(),
        ];

        parent::__construct($config);
    }
}
