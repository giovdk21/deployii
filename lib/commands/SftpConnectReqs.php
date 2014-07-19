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

    public $sftpHost = '';

    public $sftpUsername = '';

    public $sftpPassword = '';

    public $sftpPort = '22';

    public $sftpAuthMethod = 'password';

    public $sftpKeyFile = '';

    public $sftpKeyPassword = '';

    public $connections = [];


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
     * @param array                 $buildParams
     */
    public static function checkRequirements(& $buildParams) {
        // ... nothing to do here yet.
    }

} 