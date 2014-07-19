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
use app\lib\TaskRunner;
use yii\console\Exception;
use yii\helpers\Console;
use Yii;

class CopyCommand extends BaseCommand {

    /**
     * @inheritdoc
     */
    public static function run(& $cmdParams, & $params) {

        $res = true;
        $fileFrom = (!empty($cmdParams[1]) ? TaskRunner::parsePath($cmdParams[0]) : '');
        $fileTo = (!empty($cmdParams[1]) ? TaskRunner::parsePath($cmdParams[1]) : '');

        if (empty($fileFrom) || empty($fileTo)) {
            throw new Exception('copy: Origin and destination cannot be empty');
        }

        TaskRunner::$controller->stdout("Copy file: \n  ".$fileFrom." to \n  ".$fileTo);

        if (!TaskRunner::$controller->dryRun) {
            $res = copy($fileFrom, $fileTo);
        }
        else {
            TaskRunner::$controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        TaskRunner::$controller->stdout("\n");
        return $res;
    }

} 