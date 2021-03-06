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
use app\lib\SftpHelper;
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
            /** @var $connection Net_SFTP|resource */
            $connection = $controller->getConnection($connectionId);

            switch ($connParams['sftpConnectionType']) {

                case SftpHelper::TYPE_SFTP:
                    $res = $connection->chdir($dir);
                    break;

                case SftpHelper::TYPE_FTP:
                    $res = @ftp_chdir($connection, $dir);
                    break;

                default:
                    $controller->stdout("\n");
                    Log::throwException('Unsupported connection type: '.$connParams['sftpConnectionType']);
                    break;
            }

            if (!$res) {
                $controller->stdout("\n");
                Log::throwException(
                    'sftpChdir: error changing directory to '.$dir,
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