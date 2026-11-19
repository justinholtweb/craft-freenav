<?php

namespace JustinHoltWeb\FreeNav\Gql\Types\Generators;

use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;
use JustinHoltWeb\FreeNav\FreeNav;
use JustinHoltWeb\FreeNav\Gql\Interfaces\NodeInterface;
use JustinHoltWeb\FreeNav\Gql\Types\NodeType;

class NodeGenerator implements GeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $menus = FreeNav::getInstance()->getMenus()->getAllMenus();
        $types = [];

        foreach ($menus as $menu) {
            $typeName = $menu->handle . '_FreeNavNode';

            $fields = NodeInterface::getFieldDefinitions();

            $type = GqlEntityRegistry::getEntity($typeName)
                ?: GqlEntityRegistry::createEntity($typeName, new NodeType([
                    'name' => $typeName,
                    'fields' => function() use ($fields) {
                        return $fields;
                    },
                ]));

            $types[$typeName] = $type;
        }

        return $types;
    }
}
