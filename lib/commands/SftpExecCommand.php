<?php
/**
 * DeploYii - SftpExecCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\Log;
use yii\helpers\Console;
use Yii;
use Net_SFTP;

class SftpExecCommand extends BaseCommand
{


    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        $taskRunner = $this->taskRunner;
        $controller = $this->controller;

        $res = true;
        $connectionId = (!empty($cmdParams[0]) ? $cmdParams[0] : '');
        $execCommand = (!empty($cmdParams[1]) ? $cmdParams[1] : '');
        $execParams = (!empty($cmdParams[2]) ? $taskRunner->parseStringParams($cmdParams[2]) : '');
        $execHiddenParams = (!empty($cmdParams[3]) ? $cmdParams[3] : ''); // not printed out

        if (empty($connectionId) || empty($execCommand)) {
            Log::throwException('sftpExec: Please specify a valid connection id and directory');
        }

        /** @noinspection PhpUndefinedMethodInspection (provided by the SftpConnectReqs Behavior) */
        $connParams = $controller->getConnectionParams($connectionId);

        $cmdString = trim($execCommand.' '.$execParams);
        $cmdFull = trim($execCommand.' '.$execParams.' '.$execHiddenParams);

        if (!$controller->dryRun) {
            // the getConnection method is provided by the SftpConnectReqs Behavior
            /** @noinspection PhpUndefinedMethodInspection */
            /** @var $connection Net_SFTP */
            $connection = $controller->getConnection($connectionId);

            $sftpDir = $connection->pwd();
            $execOutput = $connection->exec('cd '.$sftpDir.' && '.$cmdFull);
            $execResult = $connection->getExitStatus();
        } else {
            $execResult = 0;
            $execOutput = '';
        }

        $controller->stdout(" ".$connectionId." ", $connParams['sftpLabelColor'], Console::FG_BLACK);
        $controller->stdout(" ");
        if ($execResult !== 0) {
            $this->controller->stderr("Error running ".$cmdString." ({$execResult})\n", Console::FG_RED);
        } else {
            $this->controller->stdout('Running shell command: ');
            $this->controller->stdout($execCommand.' ', Console::FG_YELLOW);
            $this->controller->stdout($execParams, Console::FG_BLUE);
            if (!empty($execOutput)) {
                $this->controller->stdout(
                    "\n".'---------------------------------------------------------------'."\n"
                );
                $this->controller->stdout(trim($execOutput));
                $this->controller->stdout(
                    "\n".'---------------------------------------------------------------'."\n"
                );
            } elseif ($this->controller->dryRun) {
                $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
            }
        }

        $controller->stdout("\n");
        return $res;
    }

} 