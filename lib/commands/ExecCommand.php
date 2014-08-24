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
use yii\helpers\Console;

class ExecCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {

        $execOutput = [];
        $taskRunner = $this->taskRunner;

        $execResult = null;
        $execCommand = (!empty($cmdParams[0]) ? $cmdParams[0] : '');
        $execParams = (!empty($cmdParams[1]) ? $taskRunner->parseStringAliases($cmdParams[1]) : '');
        $execHiddenParams = (!empty($cmdParams[2]) ? $cmdParams[2] : ''); // not printed out

        $cmdString = trim($execCommand.' '.$execParams);
        $cmdFull = trim($execCommand.' '.$execParams.' '.$execHiddenParams);

        if (!empty($execCommand)) {
            if (!$this->controller->dryRun) {
                exec($cmdFull, $execOutput, $execResult);
            } else {
                $execResult = 0;
                $execOutput = ['dry run mode: nothing really happened'];
            }

            if ($execResult !== 0) {
                $this->controller->stderr("Error running ".$cmdString." ({$execResult})\n", Console::FG_RED);
            } else {
                $this->controller->stdout('Running shell command: ');
                $this->controller->stdout($cmdString."\n", Console::FG_YELLOW);
                if (!empty($execOutput)) {
                    $this->controller->stdout(
                        '---------------------------------------------------------------'."\n"
                    );
                    $this->controller->stdout(implode("\n", $execOutput)."\n");
                    $this->controller->stdout(
                        '---------------------------------------------------------------'."\n\n"
                    );
                }
            }
        }

        return $execResult;
    }

} 