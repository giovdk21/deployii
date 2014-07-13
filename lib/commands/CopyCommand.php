<?php
/**
 * DeploYii - CopyCommand
 *
 * @link https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\BaseConsoleController;
use yii\console\Exception;
use yii\helpers\Console;
use Yii;

class CopyCommand extends BaseCommand {

    public static function run(BaseConsoleController $controller, & $cmdParams, & $params) {

        $res = true;
        $fileFrom = (!empty($cmdParams[1]) ? Yii::getAlias($cmdParams[0]) : '');
        $fileTo = (!empty($cmdParams[1]) ? Yii::getAlias($cmdParams[1]) : '');

        if (empty($fileFrom) || empty($fileTo)) {
            throw new Exception('copy: Origin and destination cannot be empty');
        }

        $controller->stdout("Copy file: \n  ".$fileFrom." to \n  ".$fileTo);

        if (!$controller->dryRun) {
            $res = copy($fileFrom, $fileTo);
        }
        else {
            $controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $controller->stdout("\n");
        return $res;
    }

} 