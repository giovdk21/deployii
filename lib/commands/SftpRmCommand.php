<?php
/**
 * DeploYii - SftpRmCommand
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

class SftpRmCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        $controller = $this->controller;

        $res = true;
        $connectionId = (!empty($cmdParams[0]) ? $cmdParams[0] : '');
        $file = (!empty($cmdParams[1]) ? $cmdParams[1] : '');

        if (empty($connectionId) || empty($file)) {
            Log::throwException('sftpRm: Please specify a valid connection id and file');
        }

        /** @noinspection PhpUndefinedMethodInspection (provided by the SftpConnectReqs Behavior) */
        $connParams = $controller->getConnectionParams($connectionId);
        $controller->stdout(" ".$connectionId." ", $connParams['sftpLabelColor'], Console::FG_BLACK);
        $controller->stdout(' Removing file ');
        $controller->stdout($file, Console::FG_CYAN);

        if (!$controller->dryRun) {
            // the getConnection method is provided by the SftpConnectReqs Behavior
            /** @noinspection PhpUndefinedMethodInspection */
            /** @var $connection Net_SFTP|resource */
            $connection = $controller->getConnection($connectionId);
            $sftpHelper = new SftpHelper($connectionId, $connection, $connParams);

            if ($sftpHelper->isFile($file)) {
                $res = $sftpHelper->delete($file, false);
                if (!$res) {
                    Log::logger()->addError(
                        'sftpRm: error removing file {file}',
                        ['file' => $file]
                    );
                }
            } else {
                $controller->stdout("\n");
                $controller->warn('sftpRm: '.$file.' is not a file');
            }
            $sftpHelper->flushCache();
        } else {
            $controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $controller->stdout("\n");
        return $res;
    }

} 