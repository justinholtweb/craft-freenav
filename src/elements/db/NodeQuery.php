<?php

namespace justinholt\freenav\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use justinholt\freenav\elements\Node;
use justinholt\freenav\enums\NodeType;

class NodeQuery extends ElementQuery
{
    public mixed $menuId = null;
    public mixed $menuHandle = null;
    public mixed $nodeType = null;
    public ?bool $hasUrl = null;
    public mixed $linkedElementId = null;
    public ?bool $visibleOnly = null;

    public function menuId(mixed $value): static
    {
        $this->menuId = $value;
        return $this;
    }

    public function menuHandle(mixed $value): static
    {
        $this->menuHandle = $value;
        return $this;
    }

    public function nodeType(mixed $value): static
    {
        if ($value instanceof NodeType) {
            $this->nodeType = $value->value;
        } else {
            $this->nodeType = $value;
        }
        return $this;
    }

    public function hasUrl(?bool $value = true): static
    {
        $this->hasUrl = $value;
        return $this;
    }

    public function linkedElementId(mixed $value): static
    {
        $this->linkedElementId = $value;
        return $this;
    }

    public function visibleOnly(?bool $value = true): static
    {
        $this->visibleOnly = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('freenav_nodes');

        $this->query->select([
            'freenav_nodes.menuId',
            'freenav_nodes.parentId',
            'freenav_nodes.linkedElementId',
            'freenav_nodes.nodeType',
            'freenav_nodes.url',
            'freenav_nodes.classes',
            'freenav_nodes.urlSuffix',
            'freenav_nodes.customAttributes',
            'freenav_nodes.data',
            'freenav_nodes.newWindow',
            'freenav_nodes.icon',
            'freenav_nodes.badge',
            'freenav_nodes.visibilityRules',
            'freenav_nodes.deletedWithMenu',
        ]);

        if ($this->menuId) {
            $this->subQuery->andWhere(Db::parseParam('freenav_nodes.menuId', $this->menuId));
        }

        if ($this->menuHandle) {
            $this->subQuery->innerJoin(
                '{{%freenav_menus}} freenav_menus',
                '[[freenav_menus.id]] = [[freenav_nodes.menuId]]'
            );
            $this->subQuery->andWhere(Db::parseParam('freenav_menus.handle', $this->menuHandle));
        }

        if ($this->nodeType) {
            $this->subQuery->andWhere(Db::parseParam('freenav_nodes.nodeType', $this->nodeType));
        }

        if ($this->hasUrl !== null) {
            if ($this->hasUrl) {
                $this->subQuery->andWhere(['not', ['freenav_nodes.nodeType' => 'passive']]);
            } else {
                $this->subQuery->andWhere(['freenav_nodes.nodeType' => 'passive']);
            }
        }

        if ($this->linkedElementId) {
            $this->subQuery->andWhere(Db::parseParam('freenav_nodes.linkedElementId', $this->linkedElementId));
        }

        return parent::beforePrepare();
    }

    protected function afterPrepare(): bool
    {
        return parent::afterPrepare();
    }

    /**
     * @inheritdoc
     * @return Node[]
     */
    protected function createElement(array $row): Node
    {
        return new Node($row);
    }
}
