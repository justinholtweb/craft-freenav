<?php

namespace justinholt\freenav\gql\types;

use craft\gql\GqlEntityRegistry;
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
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new ObjectType([
            'name' => self::getName(),
            'description' => 'A custom HTML attribute key-value pair.',
            'fields' => [
                'key' => [
                    'name' => 'key',
                    'type' => Type::string(),
                    'description' => 'The attribute name.',
                ],
                'value' => [
                    'name' => 'value',
                    'type' => Type::string(),
                    'description' => 'The attribute value.',
                ],
            ],
        ]));
    }
}
