<?php

namespace justinholt\freenav\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property int $structureId
 * @property int|null $fieldLayoutId
 * @property string $name
 * @property string $handle
 * @property string|null $instructions
 * @property string $propagationMethod
 * @property int|null $maxNodes
 * @property int|null $maxLevels
 * @property string $defaultPlacement
 * @property string|null $permissions
 * @property int $sortOrder
 * @property string|null $dateDeleted
 * @property string $uid
 */
class MenuRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%freenav_menus}}';
    }

    public function getSites(): ActiveQueryInterface
    {
        return $this->hasMany(MenuSiteRecord::class, ['menuId' => 'id']);
    }

    public function getNodes(): ActiveQueryInterface
    {
        return $this->hasMany(NodeRecord::class, ['menuId' => 'id']);
    }
}
