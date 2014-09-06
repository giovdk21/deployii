<?php
/**
 * DeploYii - SftpConnectCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\Log;
use Crypt_RSA;
use yii\helpers\Console;
use Yii;
use Net_SFTP;

class SftpConnectCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        $controller = $this->controller;

        $res = false;
        $connectionId = (!empty($cmdParams[0]) ? $cmdParams[0] : '');

        $host = $params['sftpHost'];
        $username = $params['sftpUsername'];
        $password = $params['sftpPassword'];
        $port = $params['sftpPort'];
        $timeout = $params['sftpTimeout'];
        $authMethod = $params['sftpAuthMethod'];
        $keyFile = $params['sftpKeyFile'];
        $keyPassword = $params['sftpKeyPassword'];


        if (empty($host)) {
            Log::throwException('sftpConnect: Please specify a valid host');
        }

        $connectionString = $username . '@' . $host . ':' . $port;
        $controller->stdout(" ".$connectionId." ", Console::BG_BLUE, Console::FG_BLACK);
        $controller->stdout(' Opening connection with ' . $connectionString . " ...");

        if (!$controller->dryRun) {

            $sftp = new Net_SFTP($host, $port, $timeout);

            switch ($authMethod) {

                case 'password':
                    $res = $sftp->login($username, $password);
                    break;

                case 'key':
                    $key = new Crypt_RSA();

                    if (!empty($keyPassword)) {
                        $key->setPassword($keyPassword);
                    }

                    if (!empty($keyFile) && file_exists($keyFile)) {
                        $key->loadKey(file_get_contents($keyFile));
                    } else {
                        $controller->stdout("\n");
                        Log::throwException('sftpConnect: keyFile not found ('.$keyFile.')');
                    }

                    $res = $sftp->login($username, $key);
                    break;
            }

            if ($res) {
                // the setConnection method is provided by the SftpConnectReqs Behavior
                /** @noinspection PhpUndefinedMethodInspection */
                $controller->setConnection($connectionId, $sftp);
            } else {
                $controller->stdout("\n");
                Log::throwException('Unable to connect with '.$connectionString);
            }
        } else {
            $controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $controller->stdout("\n");
        return $res;
    }

} 