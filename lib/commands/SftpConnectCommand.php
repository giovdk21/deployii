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
use yii\console\Exception;
use yii\helpers\Console;
use Yii;
use Net_SFTP;

class SftpConnectCommand extends BaseCommand
{

    private $_connectionId = '';

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        $controller = $this->controller;

        $res = false;
        $this->_connectionId = (!empty($cmdParams[0]) ? $cmdParams[0] : '');

        $connectionParams = $this->_configureFrom($params);
        /** @noinspection PhpUndefinedMethodInspection (provided by the SftpConnectReqs Behavior) */
        $controller->setConnectionParams($this->_connectionId, $connectionParams);

        $host = $connectionParams['sftpHost'];
        $username = $connectionParams['sftpUsername'];
        $password = $connectionParams['sftpPassword'];
        $port = $connectionParams['sftpPort'];
        $timeout = $connectionParams['sftpTimeout'];
        $authMethod = $connectionParams['sftpAuthMethod'];
        $keyFile = $connectionParams['sftpKeyFile'];
        $keyPassword = $connectionParams['sftpKeyPassword'];
        $bgColor = $connectionParams['sftpLabelColor'];


        if (empty($host)) {
            Log::throwException('sftpConnect: Please specify a valid host');
        }

        $connectionString = $username . '@' . $host . ':' . $port;
        $controller->stdout(" ".$this->_connectionId." ", $bgColor, Console::FG_BLACK);
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
                $controller->setConnection($this->_connectionId, $sftp);
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

    /**
     * If there are some connection parameters defined specifically for the current connection id,
     * it will take those instead of the ones available in the build script / config.
     *
     * Example:
     * 'sftpAuthMethod' => 'key',
     * 'sftpUsername'   => 'testuser',
     * 'conn1.sftpAuthMethod' => 'password',
     * 'conn1.sftpPassword'   => '...',
     *
     *
     * @param array $params The build parameters
     *
     * @return array
     */
    private function _configureFrom($params)
    {
        $res = [];

        if (empty($this->_connectionId)) {
            Log::throwException('sftpConnect: caling '.__METHOD__.' without a valid connection id set.');
        }

        foreach($params as $key => $value) {
            if (isset($params[$this->_connectionId.'.'.$key])) {
                $res[$key] = $params[$this->_connectionId.'.'.$key];
            } else {
                $res[$key] = $value;
            }
        }

        return $res;
    }

} 