<?php
/**
 * DeploYii - SaveAsJsonCommand
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
use Yii;
use yii\helpers\Json;

class SaveAsJsonCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public static function run(& $cmdParams, & $params)
    {

        $res = true;
        $filename = (!empty($cmdParams[0]) ? TaskRunner::parsePath($cmdParams[0]) : '');
        $data = (!empty($cmdParams[1]) ? $cmdParams[1] : []);

        if (empty($filename)) {
            throw new Exception('Please specify the path of the file you want to save to');
        }

        TaskRunner::$controller->stdout("Saving json file: \n  " . $filename);

        if (!TaskRunner::$controller->dryRun) {
            $res = file_put_contents($filename, Json::encode($data));
        } else {
            TaskRunner::$controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        TaskRunner::$controller->stdout("\n");
        return $res;
    }

} 