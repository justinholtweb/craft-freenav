<?php

namespace justinholt\freenav\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class FreeNavAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@justinholt/freenav/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'css/freenav.css',
        ];

        $this->js = [
            'js/FreeNavBuilder.js',
        ];

        parent::init();
    }
}
