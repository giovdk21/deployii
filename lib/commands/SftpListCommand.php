<?php
/**
 * DeploYii - SftpListCommand
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

class SftpListCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        $controller = $this->controller;

        $res = true;
        $list = [];
        $connectionId = (!empty($cmdParams[0]) ? $cmdParams[0] : '');
        $dir = (!empty($cmdParams[1]) ? $cmdParams[1] : '.');
        $recursive = (!empty($cmdParams[2]) ? $cmdParams[2] : false);
        $varName = (!empty($cmdParams[3]) ? $cmdParams[3] : $connectionId.'.list');

        if (empty($connectionId)) {
            Log::throwException('sftpList: Please specify a valid connection id');
        }

        /** @noinspection PhpUndefinedMethodInspection (provided by the SftpConnectReqs Behavior) */
        $connParams = $controller->getConnectionParams($connectionId);
        $controller->stdout(" ".$connectionId." ", $connParams['sftpLabelColor'], Console::FG_BLACK);
        $controller->stdout(' Listing directory ');
        $controller->stdout($dir, Console::FG_CYAN);

        if (!$controller->dryRun) {
            // the getConnection method is provided by the SftpConnectReqs Behavior
            /** @noinspection PhpUndefinedMethodInspection */
            /** @var $connection Net_SFTP|resource */
            $connection = $controller->getConnection($connectionId);
            $sftpHelper = new SftpHelper($connectionId, $connection, $connParams);

            $list = $sftpHelper->nlist($dir, $recursive, true);
            // TODO: use SftpHelper to store the list
        } else {
            $controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $params[$varName] = $list;

        $controller->stdout("\n");
        return $res;
    }

} 