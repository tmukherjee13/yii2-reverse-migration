<?php

namespace tmukherjee13\migration;

use yii\base\Application;
use yii\base\BootstrapInterface;

/**
 * Class Bootstrap.
 *
 */
class Bootstrap implements BootstrapInterface
{
    /**
     * Bootstrap method to be called during application bootstrap stage.
     *
     * @param Application $app the application currently running
     */
    public function bootstrap($app)
    {
        if ($app->hasModule('gii')) {
           

           
            if (!isset($app->getModule('gii')->generators['reverse-migration'])) {
                $app->getModule('gii')->generators['reverse-migration'] = 'tmukherjee13\migration\generators\migration\Generator';
            }

            // if ($app instanceof \yii\console\Application) {
            //     $app->controllerMap['giiant-batch'] = 'schmunk42\giiant\commands\BatchController';
            // }
        }
    }
}