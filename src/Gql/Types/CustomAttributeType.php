<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Gql\Types;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class CustomAttributeType
{
    public static function getName(): string
    {
        return 'FreeNavCustomAttribute';
    }

    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), fn () => new ObjectType([
            'name' => self::getName(),
            'description' => 'A custom HTML attribute key-value pair.',
            'fields' => [
                'key' => [
                    'name' => 'key',
                    'type' => Type::string(),
                    'description' => 'The attribute name.',
                    'resolve' => fn (array $source) => isset($source['key']) && is_scalar($source['key']) ? (string) $source['key'] : null,
                ],
                'value' => [
                    'name' => 'value',
                    'type' => Type::string(),
                    'description' => 'The attribute value.',
                    'resolve' => fn (array $source) => isset($source['value']) && is_scalar($source['value']) ? (string) $source['value'] : null,
                ],
            ],
        ]));
    }
}
