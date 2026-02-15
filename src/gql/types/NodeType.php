<?php

namespace justinholt\freenav\gql\types;

use craft\gql\base\ObjectType;
use justinholt\freenav\gql\interfaces\NodeInterface;

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
