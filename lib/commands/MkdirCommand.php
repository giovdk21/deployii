<?php
/**
 * DeploYii - MkdirCommand
 *
 * @link https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\BaseConsoleController;
use app\lib\TaskRunner;
use yii\console\Exception;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use Yii;

class MkdirCommand extends BaseCommand {

    public static function run(BaseConsoleController $controller, & $cmdParams, & $params) {

        $res = true;
        $path = (!empty($cmdParams[0]) ? TaskRunner::parsePath($cmdParams[0]) : '');

        if (empty($path)) {
            throw new Exception('mkdir: Path cannot be empty');
        }

        $controller->stdout('Creating directory: '.$path);

        if (!$controller->dryRun) {
            $res = FileHelper::createDirectory($path);
        }
        else {
            $controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $controller->stdout("\n");
        return $res;
    }

} 