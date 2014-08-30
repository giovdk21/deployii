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
use yii\helpers\FileHelper;

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
        '0.4' => [
            'add' => ['workspace' => 'Moved workspace directory to the home folder'],
        ],
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

    private static function _getBuildVersionChanges($scriptVersion)
    {
        $changeLog = '';

        $changes = array_reverse(self::$_buildChangeList);

        foreach ($changes as $version => $list) {

            if (version_compare($version, $scriptVersion, '>')) {
                foreach ($list as $logMessage) {
                    $changeLog .= " - [{$version}] ".$logMessage."\n";
                }
            }
        }

        return $changeLog;
    }

    /**
     * @param string $buildVersion the DeploYii version the build script has been created for
     *
     * @throws \yii\console\Exception if the build script version is outdated
     */
    public static function checkBuildVersion($buildVersion)
    {
        $changeLog = self::_getBuildVersionChanges($buildVersion);

        if (!empty($changeLog)) {
            Log::throwException(
                "Your build script is not compatible with DeploYii "
                .DEPLOYII_VERSION.":\n".$changeLog
            );
        }
    }

    /**
     * @param string $recipeVersion the DeploYii version the recipe script has been created for
     * @param string $recipeName the name of the recipe being loaded
     *
     * @throws \yii\console\Exception if the recipe script version is outdated
     */
    public static function checkRecipeVersion($recipeVersion, $recipeName)
    {
        $changeLog = self::_getBuildVersionChanges($recipeVersion);

        if (!empty($changeLog)) {
            Log::throwException(
                "Your recipe script \"{$recipeName}\" is not compatible with DeploYii "
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
                // files/directories to be added: (if no manual actions are required)
                if (empty($requiredActions) && isset($list['add'])) {
                    foreach ($list['add'] as $relPath => $logMessage) {
                        $srcPath = $homeDist.DIRECTORY_SEPARATOR.$relPath;
                        $destPath = $home.DIRECTORY_SEPARATOR.$relPath;
                        if (!is_dir($srcPath) && !file_exists($destPath) && file_exists($srcPath)) {
                            $changeLog .= " - [{$version}] Adding {$relPath} ({$logMessage})\n";
                            copy($srcPath, $destPath);
                        } elseif (is_dir($srcPath) && !is_dir($destPath)) {
                            $changeLog .= " - [{$version}] Adding directory: {$relPath} ({$logMessage})\n";
                            FileHelper::copyDirectory($srcPath, $destPath);
                        } elseif (is_dir($srcPath) && is_dir($destPath)) {
                            $requiredActions .= " - [{$version}] {$relPath}: {$logMessage}\n";
                        }
                    }
                }
                // files/directories to be removed: (if no manual actions are required)
                if (empty($requiredActions) && isset($list['rem'])) {
                    foreach ($list['rem'] as $relPath => $logMessage) {
                        $destPath = $home.DIRECTORY_SEPARATOR.$relPath;
                        if (!is_dir($destPath) && file_exists($destPath)) {
                            $changeLog .= " - [{$version}] Removing {$relPath} ({$logMessage})\n";
                            unlink($destPath);
                        } elseif (is_dir($destPath)) {
                            $requiredActions .= " - [{$version}] {$relPath}: {$logMessage}\n";
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
            sleep(2);
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