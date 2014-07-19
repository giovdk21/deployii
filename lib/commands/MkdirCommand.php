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
use app\lib\TaskRunner;
use yii\console\Exception;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use Yii;

class MkdirCommand extends BaseCommand {

    /**
     * @inheritdoc
     */
    public static function run(& $cmdParams, & $params) {

        $res = true;
        $path = (!empty($cmdParams[0]) ? TaskRunner::parsePath($cmdParams[0]) : '');

        if (empty($path)) {
            throw new Exception('mkdir: Path cannot be empty');
        }

        TaskRunner::$controller->stdout('Creating directory: '.$path);

        if (!TaskRunner::$controller->dryRun) {
            $res = FileHelper::createDirectory($path);
        }
        else {
            TaskRunner::$controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        TaskRunner::$controller->stdout("\n");
        return $res;
    }

} 