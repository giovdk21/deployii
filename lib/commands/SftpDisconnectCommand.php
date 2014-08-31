<?php
/**
 * DeploYii - SftpDisconnectCommand
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

class SftpDisconnectCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        $controller = $this->controller;

        $res = false;
        $connectionId = (!empty($cmdParams[0]) ? $cmdParams[0] : '');

        if (empty($connectionId)) {
            Log::throwException('sftpDisconnect: Please specify a valid connection id');
        }

        $controller->stdout(" ".$connectionId." ", Console::BG_BLUE, Console::FG_BLACK);
        $controller->stdout(' Closing connection ');

        if (!$controller->dryRun) {
            // the getConnection method is provided by the SftpConnectReqs Behavior
            /** @noinspection PhpUndefinedMethodInspection */
            /** @var $connection Net_SFTP */
            $connection = $controller->getConnection($connectionId);
            $connection->disconnect();
        } else {
            $controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $controller->stdout("\n");
        return $res;
    }

} 