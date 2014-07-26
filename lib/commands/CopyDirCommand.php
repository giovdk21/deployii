<?php
/**
 * DeploYii - CopyDirCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\TaskRunner;
use yii\console\Exception;
use yii\helpers\Console;
use Yii;
use yii\helpers\FileHelper;

class CopyDirCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public static function run(& $cmdParams, & $params)
    {

        $res = true;
        $toBeCopied = [];
        $baseDir = (!empty($cmdParams[0]) ? TaskRunner::parsePath($cmdParams[0]) : '');
        $destDir = (!empty($cmdParams[1]) ? TaskRunner::parsePath($cmdParams[1]) : '');
        $dirList = (!empty($cmdParams[2]) ? $cmdParams[2] : []);
        $options = (!empty($cmdParams[3]) ? $cmdParams[3] : []);

        if (empty($baseDir) || empty($destDir)) {
            throw new Exception('copyDir: Base and destination cannot be empty');
        }

        if (!is_dir($baseDir) || (file_exists($destDir) && !is_dir($destDir))) {
            throw new Exception('copyDir: Base and destination have to be directories');
        }

        // if dirList is specified but it is a string, we convert it to an array
        if (!empty($dirList) && is_string($dirList)) {
            $dirList = explode(',', $dirList);
        }

        if (empty($dirList) || !is_array($dirList)) {
            // if the $dirList array is empty we populate it with the baseDir
            $toBeCopied = [$baseDir];
        } else {
            foreach ($dirList as $dirRelPath) {
                $toBeCopied[$dirRelPath] = $baseDir.DIRECTORY_SEPARATOR
                    .TaskRunner::parseStringParams(trim($dirRelPath));
            }
        }


        foreach ($toBeCopied as $srcRelPath => $srcDirPath) {

            if (is_dir($srcDirPath)) {

                /* *
                 * if the destination directory already exists or if the
                 * $srcRelPath is a string (meaning that $dirList is not empty)
                 * we copy the source directory inside of the destination directory
                 * instead of replacing the destination directory with the base directory
                 * */
                $destDirPath = $destDir;
                if (is_dir($destDirPath) || is_string($srcRelPath)) {
                    $srcRelPath = (is_string($srcRelPath) ? $srcRelPath : basename($baseDir));
                    $destDirPath = $destDir.DIRECTORY_SEPARATOR.$srcRelPath;
                }

                TaskRunner::$controller->stdout("Copy directory: \n  ".$srcDirPath." to \n  ".$destDirPath);

                if (!TaskRunner::$controller->dryRun) {
                    FileHelper::copyDirectory($srcDirPath, $destDirPath, $options);
                } else {
                    TaskRunner::$controller->stdout(' [dry run]', Console::FG_YELLOW);
                }

                TaskRunner::$controller->stdout("\n");
            } else {
                TaskRunner::$controller->stderr("{$srcDirPath} is not a directory!\n", Console::FG_RED);
            }
        }

        return $res;
    }

} 