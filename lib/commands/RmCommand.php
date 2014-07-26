<?php
/**
 * DeploYii - RmCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\TaskRunner;
use app\lib\BaseCommand;
use yii\console\Exception;
use Yii;
use yii\helpers\Console;

class RmCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public static function run(& $cmdParams, & $params)
    {

        $filename = (!empty($cmdParams[0]) ? TaskRunner::parsePath($cmdParams[0]) : '');

        if (empty($filename)) {
            throw new Exception('rm: filename cannot be empty');
        }

        TaskRunner::$controller->stdout('Removing file: ' . $filename);

        if (!TaskRunner::$controller->dryRun) {
            @unlink($filename);
        } else {
            TaskRunner::$controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        TaskRunner::$controller->stdout("\n");
        return true;
    }

} 