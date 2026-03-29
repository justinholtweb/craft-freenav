<?php

namespace justinholt\freenav\gql\arguments;

use craft\gql\base\ElementArguments;
use GraphQL\Type\Definition\Type;

class NodeArguments extends ElementArguments
{
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'menuHandle' => [
                'name' => 'menuHandle',
                'type' => Type::string(),
                'description' => 'The handle of the menu to query nodes from.',
            ],
            'nodeType' => [
                'name' => 'nodeType',
                'type' => Type::string(),
                'description' => 'Filter by node type.',
            ],
            'level' => [
                'name' => 'level',
                'type' => Type::int(),
                'description' => 'Filter by nesting level.',
            ],
            'hasUrl' => [
                'name' => 'hasUrl',
                'type' => Type::boolean(),
                'description' => 'Filter nodes that have/don\'t have URLs.',
            ],
        ]);
    }
}
