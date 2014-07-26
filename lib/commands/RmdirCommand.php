<?php
/**
 * DeploYii - RmdirCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\TaskRunner;
use yii\console\Exception;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use Yii;

class RmdirCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public static function run(& $cmdParams, & $params)
    {

        $path = (!empty($cmdParams[0]) ? TaskRunner::parsePath($cmdParams[0]) : '');

        if (empty($path)) {
            throw new Exception('rmdir: Path cannot be empty');
        }

        TaskRunner::$controller->stdout('Removing directory: ' . $path);

        if (!TaskRunner::$controller->dryRun) {
            FileHelper::removeDirectory($path);
        } else {
            TaskRunner::$controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        TaskRunner::$controller->stdout("\n");
        return true;
    }

} 