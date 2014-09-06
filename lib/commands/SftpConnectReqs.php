<?php
/**
 * DeploYii - SftpConnectReqs
 *
 * This behavior will be attached to $this->controller when the
 * associated command is specified as a requirement.
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\Log;
use yii\base\Behavior;
use yii\helpers\Console;

class SftpConnectReqs extends Behavior
{

    /** @var array list of active connections */
    public $connections = [];

    /** @var array parameters of the active connections */
    private $_connectionParams = [];

    /**
     * @return array the list of command options => default values
     */
    public static function getCommandOptions()
    {
        return [
            // sftp hostname
            'sftpHost'        => '',
            // sftp username
            'sftpUsername'    => '',
            // sftp password
            'sftpPassword'    => '',
            // sftp port
            'sftpPort'        => '22',
            'sftpTimeout'     => '30',
            // sftp authentication method (password|key)
            'sftpAuthMethod'  => 'key',
            // path to the private key file
            'sftpKeyFile'     => '',
            // password of the key file
            'sftpKeyPassword' => '',
            'sftpLabelColor' => Console::BG_BLUE,
        ];
    }

    /**
     * This is ran on init() and should perform global requirements check;
     * see $this->taskRunner->_checkAllRequirements()
     *
     * @param array $buildParams build script parameters
     */
    public static function checkRequirements(& $buildParams)
    {
        // ... nothing to do here yet.
    }

    /**
     * @param string    $id         the connection id
     * @param \Net_SFTP $connection the Net_SFTP instance
     */
    public function setConnection($id, $connection)
    {
        $this->connections[$id] = $connection;
    }

    /**
     * @param string $id the connection id
     *
     * @return mixed
     */
    public function getConnection($id)
    {
        if (!isset($this->connections[$id])) {
            Log::throwException('Invalid connection: '.$id);
        }
        return $this->connections[$id];
    }

    /**
     * @param string $id     the connection id
     * @param array  $params the connection parameters
     */
    public function setConnectionParams($id, $params)
    {
        $this->_connectionParams[$id] = $params;
    }

    /**
     * @param string $id the connection id
     *
     * @return array
     */
    public function getConnectionParams($id)
    {
        return (isset($this->_connectionParams[$id]) ? $this->_connectionParams[$id] : []);
    }

} 