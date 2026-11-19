<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Gql\Arguments;

use CraftCms\Cms\Gql\Arguments\ElementArguments;
use JustinHoltWeb\FreeNav\Elements\Node;
use Override;

/**
 * The node query's arguments come from the query params `NodeQuery` declares
 * (`menuId`, `menuHandle`, `nodeType`, `hasUrl`, `linkedElementId`), plus Craft's element and
 * structure arguments (`level`, `descendantOf`, …).
 */
class NodeArguments extends ElementArguments
{
    #[Override]
    protected static function elementType(): string
    {
        return Node::class;
    }
}
