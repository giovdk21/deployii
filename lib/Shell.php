<?php
/**
 * DeploYii - Shell
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;

use yii\console\Exception;
use yii\helpers\FileHelper;

class Shell
{

    /**
     * Returns the user home directory.
     *
     * Adapted from the Composer Factory class
     * @license https://github.com/composer/composer/blob/master/LICENSE
     *
     * @return string the path of the home directory
     * @throws Exception if not valid home directory can be determined
     */
    public static function getHomeDir()
    {
        $home = getenv('DEPLOYII_HOME');
        if (!$home) {
            if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
                if (!getenv('APPDATA')) {
                    throw new Exception('The APPDATA or DEPLOYII_HOME environment variable must be set for DeploYii to run correctly');
                }
                $home = strtr(getenv('APPDATA'), '\\', '/').'/DeploYii';
            } else {
                if (!getenv('HOME')) {
                    throw new Exception('The HOME or DEPLOYII_HOME environment variable must be set for DeploYii to run correctly');
                }
                $home = rtrim(getenv('HOME'), '/').'/.deployii';
            }
        }

        return $home;
    }

    /**
     * Initialise the home directory:
     * - if not present, create it copying the content from the home-dist directory
     * - if present check if the home directory is up to date with the latest DeploYii version
     */
    public static function initHomeDir()
    {
        $home = self::getHomeDir();

        if (!is_dir($home)) {

            FileHelper::copyDirectory(__DIR__.'/../home-dist', $home);

            @unlink($home.'/README.md');
            VersionManager::updateHomeVersion();
        } else {
            $homeVersion = VersionManager::getHomeVersion();
            VersionManager::checkHomeVersion($homeVersion);
        }
    }

}