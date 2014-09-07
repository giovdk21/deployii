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
use app\lib\SftpHelper;
use Crypt_RSA;
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
        $connectionType = $connectionParams['sftpConnectionType'];
        $bgColor = $connectionParams['sftpLabelColor'];


        if (empty($host)) {
            Log::throwException('sftpConnect: Please specify a valid host');
        }

        $connectionString = $username . '@' . $host . ':' . $port;
        $controller->stdout(" ".$this->_connectionId." ", $bgColor, Console::FG_BLACK);
        $controller->stdout(' Opening connection with ' . $connectionString . " ...");

        if (!$controller->dryRun) {

            switch ($connectionType) {

                case SftpHelper::TYPE_SFTP:

                    $connection = new Net_SFTP($host, $port, $timeout);

                    switch ($authMethod) {

                        case SftpHelper::AUTH_METHOD_PASSWORD:
                            $res = $connection->login($username, $password);
                            break;

                        case SftpHelper::AUTH_METHOD_KEY:
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

                            $res = $connection->login($username, $key);
                            break;
                    }
                    break;

                case SftpHelper::TYPE_FTP:
                    // $authMethod ignored: it is always password based for FTP connections

                    $res = false;
                    $connection = ftp_connect($host, $port, $timeout);
                    if ($connection) {
                        $res = ftp_login($connection, $username, $password);
                    }

                    // the FTP server must support passive mode as we are using the FTP wrappers:
                    /* @link http://php.net/manual/en/wrappers.ftp.php */
                    if ($res && !@ftp_pasv($connection, true)) { // enabling passive mode
                        ftp_close($connection);
                        $controller->stdout("\n");
                        Log::throwException('The server does not support passive mode');
                    }

                    break;

                default:
                    $controller->stdout("\n");
                    Log::throwException('Unsupported connection type: '.$connectionType);
                    break;
            }



            if ($res) {
                // the setConnection method is provided by the SftpConnectReqs Behavior
                /** @noinspection PhpUndefinedMethodInspection */
                $controller->setConnection($this->_connectionId, $connection);
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
     * 'conn1.sftpAuthMethod' => 'password', // "conn1" is the connection id
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

        $requirements = new SftpConnectReqs();
        $cmdOptions = $requirements->getCommandOptions();

        foreach($cmdOptions as $key => $value) {
            if (isset($params[$this->_connectionId.'.'.$key])) {
                $res[$key] = $params[$this->_connectionId.'.'.$key];
            } else {
                $res[$key] = $params[$key];
            }
        }

        return $res;
    }

} 