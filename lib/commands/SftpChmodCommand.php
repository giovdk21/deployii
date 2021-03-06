<?php
/**
 * DeploYii - SftpChmodCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\Log;
use app\lib\SftpHelper;
use yii\helpers\Console;
use Yii;
use Net_SFTP;

class SftpChmodCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        $controller = $this->controller;

        $res = true;
        $connectionId = (!empty($cmdParams[0]) ? $cmdParams[0] : '');
        $permList = (!empty($cmdParams[1]) ? $cmdParams[1] : []);

        if (empty($connectionId)) {
            Log::throwException('sftpChmod: Please specify a valid connection id');
        }

        /** @noinspection PhpUndefinedMethodInspection (provided by the SftpConnectReqs Behavior) */
        $connParams = $controller->getConnectionParams($connectionId);

        foreach ($permList as $mode => $pathList) {

            $mode = (is_string($mode) ? octdec((int)$mode) : $mode);

            foreach ($pathList as $path) {
//                $path = $taskRunner->parsePath($path); // TODO: parse parameters

                $controller->stdout(" ".$connectionId." ", $connParams['sftpLabelColor'], Console::FG_BLACK);
                $controller->stdout(" Changing permissions of {$path} to ");
                $controller->stdout('0'.decoct($mode), Console::FG_CYAN);

                if (!$this->controller->dryRun) {
                    // the getConnection method is provided by the SftpConnectReqs Behavior
                    /** @noinspection PhpUndefinedMethodInspection */
                    /** @var $connection Net_SFTP|resource */
                    $connection = $controller->getConnection($connectionId);
                    $sftpHelper = new SftpHelper($connectionId, $connection, $connParams);

                    $res = $sftpHelper->chmod($mode, $path, false);
                } else {
                    $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
                }

                $this->controller->stdout("\n");
            }
        }

        if (!$this->controller->dryRun) {
            /** @noinspection PhpUndefinedVariableInspection */
            $sftpHelper->flushCache('stat');
        }

        return $res;
    }

} 