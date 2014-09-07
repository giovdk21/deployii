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
use app\lib\SftpHelper;
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

        /** @noinspection PhpUndefinedMethodInspection (provided by the SftpConnectReqs Behavior) */
        $connParams = $controller->getConnectionParams($connectionId);
        $controller->stdout(" ".$connectionId." ", $connParams['sftpLabelColor'], Console::FG_BLACK);
        $controller->stdout(' Closing connection ');

        if (!$controller->dryRun) {
            // the getConnection method is provided by the SftpConnectReqs Behavior
            /** @noinspection PhpUndefinedMethodInspection */
            /** @var $connection Net_SFTP|resource */
            $connection = $controller->getConnection($connectionId);


            switch ($connParams['sftpConnectionType']) {

                case SftpHelper::TYPE_SFTP:
                    $connection->disconnect();
                    break;

                case SftpHelper::TYPE_FTP:
                    ftp_close($connection);
                    break;

                default:
                    $controller->stdout("\n");
                    Log::throwException('Unsupported connection type: '.$connParams['sftpConnectionType']);
                    break;
            }
        } else {
            $controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $controller->stdout("\n");
        return $res;
    }

} 