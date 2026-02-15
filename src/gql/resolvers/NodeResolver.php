<?php

namespace justinholt\freenav\gql\resolvers;

use craft\gql\base\ElementResolver;
use justinholt\freenav\elements\db\NodeQuery;
use justinholt\freenav\elements\Node;

class NodeResolver extends ElementResolver
{
    public static function prepareQuery(mixed $source, array $arguments, ?string $fieldName = null): mixed
    {
        if ($source === null) {
            $query = Node::find();
        } else {
            $query = $source->getChildren();
        }

        if (!$query instanceof NodeQuery) {
            $query = Node::find();
        }

        foreach ($arguments as $key => $value) {
            if (method_exists($query, $key)) {
                $query->$key($value);
            } elseif (property_exists($query, $key)) {
                $query->$key = $value;
            }
        }

        return $query;
    }
}
