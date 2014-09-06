<?php
/**
 * DeploYii - SftpMkdirCommand
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

class SftpMkdirCommand extends BaseCommand
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
        $mode = (!empty($cmdParams[2]) ? $cmdParams[2] : -1);
        $recursive = (!empty($cmdParams[3]) ? $cmdParams[3] : true);

        if (empty($connectionId) || empty($dir)) {
            Log::throwException('sftpMkdir: Please specify a valid connection id and directory');
        }

        $controller->stdout(" ".$connectionId." ", Console::BG_BLUE, Console::FG_BLACK);
        $controller->stdout(' Creating directory ');
        $controller->stdout($dir, Console::FG_CYAN);

        if (!$controller->dryRun) {
            // the getConnection method is provided by the SftpConnectReqs Behavior
            /** @noinspection PhpUndefinedMethodInspection */
            /** @var $connection Net_SFTP */
            $connection = $controller->getConnection($connectionId);
            $res = $connection->mkdir($dir, $mode, $recursive);
        } else {
            $controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $controller->stdout("\n");
        return $res;
    }

} 