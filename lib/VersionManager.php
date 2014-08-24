<?php
/**
 * DeploYii - VersionManager
 *
 * The VersionManager is used to check the compatibility between the running version
 * of DeploYii and the version the build script has been created for.
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;


use yii\helpers\Console;

class VersionManager
{

    /** @var array list of changes that break compatibility between DeploYii and the build script; newer on top */
    private static $_buildChangeList = [
        // (newer on top)
        '0.4' => ['changed parameters order of the copyDir command'],
        '0.3' => ['loadJson prefix separator is now a dot'],
        '0.2' => [
            'renamed @scripts alias to @buildScripts',
            'changed require list format from "name"=>"type" to "name--type"',
        ],
    ];

    /** @var array list of changes that need to be performed to keep the DeploYii home folder up to date */
    private static $_homeChangeList = [
//        '0.3' => [
//            'add' => [],
//            'mod' => [
//                'templates/build.tpl.php' => 'Set deployiiVersion to 0.3',
//                'templates/config.tpl.php' => 'Added build configuration template file',
//                'templates/gitignore.tpl' => 'Added gitignore template file',
//            ],
//            'rem' => [],
//        ],
    ];

    /**
     * @param string $buildVersion the DeploYii version the build script has been created for
     *
     * @throws \yii\console\Exception if the build script version is outdated
     */
    public static function checkBuildVersion($buildVersion)
    {
        $changeLog = '';

        $changes = array_reverse(self::$_buildChangeList);

        foreach ($changes as $version => $list) {

            if (version_compare($version, $buildVersion, '>')) {
                foreach ($list as $logMessage) {
                    $changeLog .= " - [{$version}] ".$logMessage."\n";
                }
            }
        }

        if (!empty($changeLog)) {
            Log::throwException(
                "Your build script is not compatible with DeploYii "
                .DEPLOYII_VERSION.":\n".$changeLog
            );
        }
    }

    /**
     * For each version that is newer of the current DeploYii home version and that requires
     * changes, tells the user which changes need to be performed manually and if possible
     * updates it automatically.
     *
     * To update a file it is possible to amend it manually or to remove it; if the file is missing
     * it will be replaced with the original one from the home-dist folder.
     *
     * If some manual changes are required, it is possible to simply delete the VERSION file once
     * the changes have been applied.
     *
     * @param string $homeVersion The DeploYii home version
     *
     * @throws \yii\console\Exception if the home folder requires to be updated manually
     */
    public static function checkHomeVersion($homeVersion)
    {

        $changeLog = '';
        $requiredActions = '';
        $home = Shell::getHomeDir();
        $homeDist = __DIR__.'/../home-dist';

        $changes = array_reverse(self::$_homeChangeList);

        foreach ($changes as $version => $list) {

            if (version_compare($version, $homeVersion, '>')) {
                // files to be manually updated by the user:
                if (isset($list['mod'])) {
                    foreach ($list['mod'] as $relFilePath => $logMessage) {
                        // If the destination file does not exists, add it from the dist folder
                        // instead of requesting the user to manually update it:
                        $destFile = $home.DIRECTORY_SEPARATOR.$relFilePath;
                        if (!file_exists($destFile)) {
                            $list['add'][$relFilePath] = $logMessage;
                            unset($list['mod'][$relFilePath]);
                        } else {
                            $requiredActions .= " - [{$version}] {$relFilePath}: {$logMessage}\n";
                        }
                    }
                }
                // files to be added: (if no manual actions are required)
                if (empty($requiredActions) && isset($list['add'])) {
                    foreach ($list['add'] as $relFilePath => $logMessage) {
                        $srcFile = $homeDist.DIRECTORY_SEPARATOR.$relFilePath;
                        $destFile = $home.DIRECTORY_SEPARATOR.$relFilePath;
                        if (!file_exists($destFile) && file_exists($srcFile)) {
                            $changeLog .= " - [{$version}] Adding {$relFilePath} ({$logMessage})\n";
                            copy($srcFile, $destFile);
                        }
                    }
                }
                // files to be removed: (if no manual actions are required)
                if (empty($requiredActions) && isset($list['rem'])) {
                    foreach ($list['rem'] as $relFilePath => $logMessage) {
                        $destFile = $home.DIRECTORY_SEPARATOR.$relFilePath;
                        if (file_exists($destFile)) {
                            $changeLog .= " - [{$version}] Removing {$relFilePath} ({$logMessage})\n";
                            unlink($destFile);
                        }
                    }
                }
            }
        }

        if (!empty($requiredActions)) {
            Log::throwException(
                "Your DeploYii home folder needs to be manually updated to "
                .DEPLOYII_VERSION.":\n".$requiredActions
                ."When done delete the VERSION file and run DeploYii again.\n"
            );
        } elseif (!empty($changeLog)) {
            self::updateHomeVersion();

            $message = "Your DeploYii home folder has been updated to: ".DEPLOYII_VERSION.":\n".$changeLog;
            Log::logger()->addInfo($message);

            Console::stdout(
                "---------------------------------------------------------------\n"
                .$message
                ."---------------------------------------------------------------\n"
            );
            sleep(1);
        }
    }

    /**
     * Update the version number of the home folder with the current DeploYii version
     */
    public static function updateHomeVersion()
    {
        file_put_contents(Shell::getHomeDir().DIRECTORY_SEPARATOR.'VERSION', DEPLOYII_VERSION);
    }

    /**
     * @return string The DeploYii home version
     */
    public static function getHomeVersion()
    {
        $versionFile = Shell::getHomeDir().'/VERSION';

        if (!file_exists($versionFile)) {
            self::updateHomeVersion();
        }

        return trim(file_get_contents($versionFile));
    }

} 