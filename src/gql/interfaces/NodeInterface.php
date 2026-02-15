<?php

namespace justinholt\freenav\gql\interfaces;

use Craft;
use craft\gql\base\InterfaceType as BaseInterfaceType;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeManager;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;
use justinholt\freenav\gql\types\CustomAttributeType;
use justinholt\freenav\gql\types\generators\NodeGenerator;

class NodeInterface extends BaseInterfaceType
{
    public static function getTypeGenerator(): string
    {
        return NodeGenerator::class;
    }

    public static function getType($fields = null): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => self::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all FreeNav nodes.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));

        NodeGenerator::generateTypes();

        return $type;
    }

    public static function getName(): string
    {
        return 'FreeNavNodeInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return TypeManager::prepareFieldDefinitions(array_merge(
            parent::getFieldDefinitions(),
            [
                'nodeType' => [
                    'name' => 'nodeType',
                    'type' => Type::string(),
                    'description' => 'The type of the node (entry, category, asset, product, custom, passive, site).',
                ],
                'url' => [
                    'name' => 'url',
                    'type' => Type::string(),
                    'description' => 'The URL of the node.',
                ],
                'classes' => [
                    'name' => 'classes',
                    'type' => Type::string(),
                    'description' => 'CSS classes for the node.',
                ],
                'urlSuffix' => [
                    'name' => 'urlSuffix',
                    'type' => Type::string(),
                    'description' => 'URL suffix appended to the node URL.',
                ],
                'customAttributes' => [
                    'name' => 'customAttributes',
                    'type' => Type::listOf(CustomAttributeType::getType()),
                    'description' => 'Custom HTML attributes for the node.',
                ],
                'newWindow' => [
                    'name' => 'newWindow',
                    'type' => Type::boolean(),
                    'description' => 'Whether the link opens in a new window.',
                ],
                'icon' => [
                    'name' => 'icon',
                    'type' => Type::string(),
                    'description' => 'Icon class for the node.',
                ],
                'badge' => [
                    'name' => 'badge',
                    'type' => Type::string(),
                    'description' => 'Badge text for the node.',
                ],
                'data' => [
                    'name' => 'data',
                    'type' => Type::string(),
                    'description' => 'JSON data associated with the node.',
                ],
                'active' => [
                    'name' => 'active',
                    'type' => Type::boolean(),
                    'description' => 'Whether the node is currently active.',
                    'resolve' => function ($source) {
                        return $source->isActive();
                    },
                ],
                'menuHandle' => [
                    'name' => 'menuHandle',
                    'type' => Type::string(),
                    'description' => 'The handle of the menu this node belongs to.',
                    'resolve' => function ($source) {
                        return $source->getMenu()->handle;
                    },
                ],
                'menuName' => [
                    'name' => 'menuName',
                    'type' => Type::string(),
                    'description' => 'The name of the menu this node belongs to.',
                    'resolve' => function ($source) {
                        return $source->getMenu()->name;
                    },
                ],
                'children' => [
                    'name' => 'children',
                    'type' => Type::listOf(static::getType()),
                    'description' => 'The child nodes.',
                    'args' => [
                        'limit' => [
                            'name' => 'limit',
                            'type' => Type::int(),
                            'description' => 'Limit the number of children returned.',
                        ],
                        'level' => [
                            'name' => 'level',
                            'type' => Type::int(),
                            'description' => 'Filter children by level.',
                        ],
                    ],
                    'resolve' => function ($source, array $arguments) {
                        $query = $source->getChildren();

                        if (isset($arguments['limit'])) {
                            $query->limit($arguments['limit']);
                        }

                        if (isset($arguments['level'])) {
                            $query->level($arguments['level']);
                        }

                        return $query->all();
                    },
                ],
            ]
        ), self::getName());
    }
}
