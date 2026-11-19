<?php

namespace JustinHoltWeb\FreeNav\Gql\Queries;

use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;
use JustinHoltWeb\FreeNav\Gql\Arguments\NodeArguments;
use JustinHoltWeb\FreeNav\Gql\Interfaces\NodeInterface;
use JustinHoltWeb\FreeNav\Gql\Resolvers\NodeResolver;
use JustinHoltWeb\FreeNav\Helpers\GqlHelper;

class NodeQuery extends Query
{
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQueryFreeNavNodes()) {
            return [];
        }

        return [
            'freeNavNodes' => [
                'type' => Type::listOf(NodeInterface::getType()),
                'args' => NodeArguments::getArguments(),
                'resolve' => NodeResolver::class . '::resolve',
                'description' => 'This query is used to query for FreeNav nodes.',
            ],
            'freeNavNodeCount' => [
                'type' => Type::nonNull(Type::int()),
                'args' => NodeArguments::getArguments(),
                'resolve' => NodeResolver::class . '::resolveCount',
                'description' => 'This query is used to return the number of FreeNav nodes.',
            ],
            'freeNavNode' => [
                'type' => NodeInterface::getType(),
                'args' => NodeArguments::getArguments(),
                'resolve' => NodeResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a single FreeNav node.',
            ],
        ];
    }
}
