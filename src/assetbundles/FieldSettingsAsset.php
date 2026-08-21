<?php

namespace wmd\sectionandproducttype\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;


/**
 * Assets for the Section, Product Type and Tag Group field settings screens.
 */
class FieldSettingsAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/OptionFilter.js',
        ];

        parent::init();
    }
}
