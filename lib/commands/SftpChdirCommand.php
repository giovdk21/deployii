<?php
/**
 * DeploYii - SftpChdirCommand
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

class SftpChdirCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        $controller = $this->controller;

        $res = true;
        $connectionId = (!empty($cmdParams[0]) ? $cmdParams[0] : '');
        $dir = (!empty($cmdParams[1]) ? $cmdParams[1] : '');

        if (empty($connectionId) || empty($dir)) {
            Log::throwException('sftpChdir: Please specify a valid connection id and directory');
        }

        /** @noinspection PhpUndefinedMethodInspection (provided by the SftpConnectReqs Behavior) */
        $connParams = $controller->getConnectionParams($connectionId);
        $controller->stdout(" ".$connectionId." ", $connParams['sftpLabelColor'], Console::FG_BLACK);
        $controller->stdout(' Changing directory to ');
        $controller->stdout($dir, Console::FG_CYAN);

        if (!$controller->dryRun) {
            // the getConnection method is provided by the SftpConnectReqs Behavior
            /** @noinspection PhpUndefinedMethodInspection */
            /** @var $connection Net_SFTP */
            $connection = $controller->getConnection($connectionId);
            $res = $connection->chdir($dir);
            if (!$res) {
                Log::logger()->addError(
                    'sftpChdir: error changing directory to {dir}',
                    ['dir' => $dir]
                );
            }
        } else {
            $controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $controller->stdout("\n");
        return $res;
    }

} 