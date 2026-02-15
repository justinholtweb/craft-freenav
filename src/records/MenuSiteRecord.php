<?php

namespace justinholt\freenav\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $menuId
 * @property int $siteId
 * @property bool $enabled
 */
class MenuSiteRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%freenav_menu_sites}}';
    }
}
