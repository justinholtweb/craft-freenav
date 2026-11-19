<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Gql\Types\Generators;

use CraftCms\Cms\Gql\Contracts\GeneratorInterface;
use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\Types\Elements\Element as ElementType;
use CraftCms\Cms\Gql\Types\Generators\Generator;
use CraftCms\Cms\Support\Facades\Gql;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Gql\Interfaces\NodeInterface;
use JustinHoltWeb\FreeNav\Services\Menus;

/**
 * One GraphQL type per menu, `{menuHandle}_FreeNavNode`, with the custom fields of the menu's layout.
 */
class NodeGenerator extends Generator implements GeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $types = [];

        foreach (app(Menus::class)->getAllMenus() as $menu) {
            $typeName = Node::gqlTypeName($menu);

            $types[$typeName] = GqlEntityRegistry::getOrCreate($typeName, fn () => new ElementType([
                'name' => $typeName,
                'interfaces' => [NodeInterface::getType()],
                'fields' => function () use ($menu, $typeName) {
                    $contentFields = self::getContentFields($menu->getFieldLayout());

                    return Gql::prepareFieldDefinitions(array_merge(NodeInterface::getFieldDefinitions(), $contentFields), $typeName);
                },
            ]));
        }

        return $types;
    }
}
