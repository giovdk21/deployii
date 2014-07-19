<?php
/**
 * DeploYii - VersionManager
 *
 * The VersionManager is used to check the compatibility between the running version
 * of DeploYii and the version the build script has been created for.
 *
 * @link https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;


use yii\console\Exception;

class VersionManager {

    /** @var array list of changes that break compatibility between DeploYii and the build script; newer on top */
    private static  $_changeList = [
        // newer on top
        // '0.3' => ['...'],
        '0.2' => [
            'renamed @scripts alias to @buildScripts',
            'changed require list format from "name"=>"type" to "name--type"',
        ],
    ];

    /**
     * @param string $buildVersion the DeploYii version the build script has been created for
     *
     * @throws \yii\console\Exception if the build script version is outdated
     */
    public static function checkBuildVersion($buildVersion) {
        $changeLog = '';

        $changes = array_reverse(self::$_changeList);

        foreach ($changes as $version => $list) {

            if (version_compare($version, $buildVersion, '>')) {
                foreach ($list as $logMessage) {
                    $changeLog.= " - [{$version}] ".$logMessage."\n";
                }
            }
        }

        if (!empty($changeLog)) {
            throw new Exception(
                "Your build script is not compatible with DeploYii "
                .DEPLOYII_VERSION.":\n".$changeLog
            );
        }
    }

} 