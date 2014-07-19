<?php
/**
 * DeploYii - BaseCommand
 *
 * @link https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;


class BaseCommand {

    /**
     * @param array $cmdParams parameters passed to the command
     * @param array $params build script parameters
     *
     * @return bool
     */
    public static function run(& $cmdParams, & $params) {
        return true;
    }

} 