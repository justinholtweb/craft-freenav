<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Gql\Interfaces;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\Interfaces\Structure;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Cms\Support\Json;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Gql\Types\CustomAttributeType;
use JustinHoltWeb\FreeNav\Gql\Types\Generators\NodeGenerator;
use Override;

/**
 * `FreeNavNodeInterface`, implemented by one `{menuHandle}_FreeNavNode` type per menu.
 */
class NodeInterface extends Structure
{
    #[Override]
    public static function getTypeGenerator(): string
    {
        return NodeGenerator::class;
    }

    #[Override]
    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => self::getName(),
            'fields' => self::class.'::getFieldDefinitions',
            'description' => 'This is the interface implemented by all FreeNav nodes.',
            'resolveType' => self::class.'::resolveElementTypeName',
        ]));

        NodeGenerator::generateTypes();

        return $type;
    }

    #[Override]
    protected static function elementType(): string
    {
        return Node::class;
    }

    #[Override]
    public static function getName(): string
    {
        return 'FreeNavNodeInterface';
    }

    #[Override]
    public static function getFieldDefinitions(): array
    {
        return Gql::prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'nodeType' => [
                'name' => 'nodeType',
                'type' => Type::string(),
                'description' => 'The type of the node (entry, asset, product, custom, passive, site).',
                'resolve' => fn (Node $source) => $source->nodeType,
            ],
            'url' => [
                'name' => 'url',
                'type' => Type::string(),
                'description' => 'The URL of the node.',
                'resolve' => fn (Node $source) => $source->getUrl(),
            ],
            'classes' => [
                'name' => 'classes',
                'type' => Type::string(),
                'description' => 'CSS classes for the node.',
                'resolve' => fn (Node $source) => $source->classes,
            ],
            'urlSuffix' => [
                'name' => 'urlSuffix',
                'type' => Type::string(),
                'description' => 'URL suffix appended to the node URL.',
                'resolve' => fn (Node $source) => $source->urlSuffix,
            ],
            'customAttributes' => [
                'name' => 'customAttributes',
                'type' => Type::listOf(CustomAttributeType::getType()),
                'description' => 'Custom HTML attributes for the node. Unsafe names and non-text values are left out, as when the menu renders.',
                'resolve' => fn (Node $source) => array_values(array_filter(
                    Node::safeCustomAttributes($source->getCustomAttributesArray()) ?? [],
                    fn ($attr) => ! empty($attr['key']),
                )),
            ],
            'newWindow' => [
                'name' => 'newWindow',
                'type' => Type::boolean(),
                'description' => 'Whether the link opens in a new window.',
                'resolve' => fn (Node $source) => $source->newWindow,
            ],
            'icon' => [
                'name' => 'icon',
                'type' => Type::string(),
                'description' => 'Icon class for the node.',
                'resolve' => fn (Node $source) => $source->icon,
            ],
            'badge' => [
                'name' => 'badge',
                'type' => Type::string(),
                'description' => 'Badge text for the node.',
                'resolve' => fn (Node $source) => $source->badge,
            ],
            'data' => [
                'name' => 'data',
                'type' => Type::string(),
                'description' => 'JSON data associated with the node.',
                'resolve' => fn (Node $source) => ($data = $source->getDataArray()) !== [] ? Json::encode($data) : null,
            ],
            'active' => [
                'name' => 'active',
                'type' => Type::boolean(),
                'description' => 'Whether the node is currently active.',
                'resolve' => fn (Node $source) => $source->isActive(),
            ],
            'menuHandle' => [
                'name' => 'menuHandle',
                'type' => Type::string(),
                'description' => 'The handle of the menu this node belongs to.',
                'resolve' => fn (Node $source) => $source->getMenuHandle(),
            ],
            'menuName' => [
                'name' => 'menuName',
                'type' => Type::string(),
                'description' => 'The name of the menu this node belongs to.',
                'resolve' => fn (Node $source) => $source->getMenuName(),
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
                'resolve' => function (Node $source, array $arguments) {
                    $query = Node::find()->descendantOf($source)->descendantDist(1)->siteId($source->siteId);

                    if (isset($arguments['limit'])) {
                        $query->limit((int) $arguments['limit']);
                    }

                    if (isset($arguments['level'])) {
                        $query->level((int) $arguments['level']);
                    }

                    return $query->all();
                },
            ],
        ]), self::getName());
    }
}
