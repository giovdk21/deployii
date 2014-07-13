<?php
/**
 * DeploYii - RmdirCommand
 *
 * @link https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\BaseConsoleController;
use yii\console\Exception;
use yii\helpers\FileHelper;
use Yii;

class RmdirCommand extends BaseCommand {

    public static function run(BaseConsoleController $controller, & $cmdParams, & $params) {

        $path = (!empty($cmdParams[0]) ? Yii::getAlias($cmdParams[0]) : '');

        if (empty($path)) {
            throw new Exception('rmdir: Path cannot be empty');
        }

        $controller->stdout('Removing directory: '.$path);

        if (!$controller->dryRun) {
            FileHelper::removeDirectory($path);
        }

        return true;
    }

} 