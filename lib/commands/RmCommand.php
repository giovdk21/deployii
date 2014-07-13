<?php
/**
 * DeploYii - RmCommand
 *
 * @link https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\TaskRunner;
use app\lib\BaseCommand;
use app\lib\BaseConsoleController;
use yii\console\Exception;
use Yii;

class RmCommand extends BaseCommand {

    public static function run(BaseConsoleController $controller, & $cmdParams, & $params) {

        $filename = (!empty($cmdParams[0]) ? TaskRunner::parsePath($cmdParams[0]) : '');

        if (empty($filename)) {
            throw new Exception('rm: filename cannot be empty');
        }

        $controller->stdout('Removing file: '.$filename);

        if (!$controller->dryRun) {
            @unlink($filename);
        }

        $controller->stdout("\n");
        return true;
    }

} 