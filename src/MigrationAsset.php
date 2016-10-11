<?php


namespace tmukherjee13\migration;

use yii\web\AssetBundle;

/**
 * This declares the asset files required by Reverse Migration Gui.
 *
 * @author Tarun Mukherjee <tmukherjee13@gmail.com>
 * @since 3.0
 */
class MigrationAsset extends AssetBundle
{
    public $sourcePath = '@tmukherjee13/migration/src/assets';
    public $css = [
        'main.css',
    ];
    public $js = [
        'app.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap\BootstrapAsset',
        'yii\bootstrap\BootstrapPluginAsset',
        // 'yii\gii\TypeAheadAsset',
    ];
}
