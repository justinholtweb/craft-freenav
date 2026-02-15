<?php

namespace justinholt\freenav\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $menuId
 * @property int|null $parentId
 * @property int|null $linkedElementId
 * @property string $nodeType
 * @property string|null $url
 * @property string|null $classes
 * @property string|null $urlSuffix
 * @property string|null $customAttributes
 * @property string|null $data
 * @property bool $newWindow
 * @property string|null $icon
 * @property string|null $badge
 * @property string|null $visibilityRules
 * @property bool|null $deletedWithMenu
 * @property string $uid
 */
class NodeRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%freenav_nodes}}';
    }
}
