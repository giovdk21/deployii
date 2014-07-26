<?php
/**
 * DeploYii - ExecCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\TaskRunner;
use yii\helpers\Console;

class ExecCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public static function run(& $cmdParams, & $params)
    {

        $execOutput = [];
        $execResult = null;
        $execCommand = (!empty($cmdParams[0]) ? $cmdParams[0] : '');
        $execParams = (!empty($cmdParams[1]) ? TaskRunner::parseStringAliases($cmdParams[1]) : '');
        $execHiddenParams = (!empty($cmdParams[2]) ? $cmdParams[2] : ''); // not printed out

        $cmdString = trim($execCommand.' '.$execParams);
        $cmdFull = trim($execCommand.' '.$execParams.' '.$execHiddenParams);

        if (!empty($execCommand)) {
            if (!TaskRunner::$controller->dryRun) {
                exec($cmdFull, $execOutput, $execResult);
            } else {
                $execResult = 0;
                $execOutput = ['dry run mode: nothing really happened'];
            }

            if ($execResult !== 0) {
                TaskRunner::$controller->stderr("Error running ".$cmdString." ({$execResult})\n", Console::FG_RED);
            } else {
                TaskRunner::$controller->stdout('Running shell command: ');
                TaskRunner::$controller->stdout($cmdString."\n", Console::FG_YELLOW);
                if (!empty($execOutput)) {
                    TaskRunner::$controller->stdout(
                        '---------------------------------------------------------------'."\n"
                    );
                    TaskRunner::$controller->stdout(implode("\n", $execOutput)."\n");
                    TaskRunner::$controller->stdout(
                        '---------------------------------------------------------------'."\n\n"
                    );
                }
            }
        }

        return $execResult;
    }

} 