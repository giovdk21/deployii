<?php
/**
 * DeploYii - SftpConnectReqs
 *
 * @link https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use yii\base\Behavior;

class SftpConnectReqs extends Behavior {

    /** @var string sftp hostname */
    public $sftpHost = '';

    /** @var string sftp username */
    public $sftpUsername = '';

    /** @var string sftp password */
    public $sftpPassword = '';

    /** @var string sftp port */
    public $sftpPort = '22';

    /** @var string sftp authentication method (password|key) */
    public $sftpAuthMethod = 'password';

    /** @var string path to the private key file */
    public $sftpKeyFile = '';

    /** @var string password of the key file */
    public $sftpKeyPassword = '';

    /** @var array list of active connections */
    public $connections = [];

    /**
     * @return array the list of command options
     */
    public static function getCommandOptions() {
        return [
            'sftpHost',
            'sftpUsername',
            'sftpPassword',
            'sftpPort',
            'sftpAuthMethod',
            'sftpKeyFile',
            'sftpKeyPassword',
        ];
    }


    /**
     * This is ran on init() and should perform global requirements check;
     * see TaskRunner::_checkAllRequirements()
     *
     * @param array $buildParams build script parameters
     */
    public static function checkRequirements(& $buildParams) {
        // ... nothing to do here yet.
    }

} 