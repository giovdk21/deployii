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
use yii\console\Exception;
use yii\helpers\Console;
use Yii;
use yii\helpers\FileHelper;

class CopyDirCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {

        $res = true;
        $taskRunner = $this->taskRunner;

        $toBeCopied = [];
        $srcDirList = (!empty($cmdParams[0]) ? $cmdParams[0] : []);
        $destDir = (!empty($cmdParams[1]) ? $taskRunner->parsePath($cmdParams[1]) : '');
        $srcBaseDir = (!empty($cmdParams[2]) ? $taskRunner->parsePath($cmdParams[2]) : '');
        $options = (!empty($cmdParams[3]) ? $cmdParams[3] : []);

        if (empty($srcDirList) || empty($destDir)) {
            throw new Exception('copyDir: Base and destination cannot be empty');
        }

        if ((!empty($srcBaseDir) && !is_dir($srcBaseDir)) || (file_exists($destDir) && !is_dir($destDir))) {
            throw new Exception('copyDir: Base and destination have to be directories');
        }

        // if srcDirList is specified but it is a string, we convert it to an array
        if (!empty($srcDirList) && is_string($srcDirList)) {
            $srcDirList = explode(',', $srcDirList);
        }

        foreach ($srcDirList as $dirPath) {

            $parsedPath = $taskRunner->parseStringAliases(trim($dirPath));

            if (!empty($srcBaseDir)) {
                $toBeCopied[$parsedPath] = $srcBaseDir.DIRECTORY_SEPARATOR.$parsedPath;
            } else {
                $toBeCopied[] = $parsedPath;
            }
        }


        foreach ($toBeCopied as $srcRelPath => $srcDirPath) {

            if (is_dir($srcDirPath)) {

                /* *
                 * if the destination directory already exists or if we are
                 * copying more than one directory, we copy the source directory inside
                 * of the destination directory instead of replacing the destination
                 * directory with it
                 * */
                $destDirPath = $destDir;
                if (is_dir($destDirPath) || count($toBeCopied) > 1) {
                    $srcRelPath = (!empty($srcBaseDir) ? $srcRelPath : basename($srcDirPath));
                    $destDirPath = $destDir.DIRECTORY_SEPARATOR.$srcRelPath;
                }

                $this->controller->stdout("Copy directory: \n  ".$srcDirPath." to \n  ".$destDirPath);

                if (!$this->controller->dryRun) {
                    FileHelper::copyDirectory($srcDirPath, $destDirPath, $options);
                } else {
                    $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
                }

                $this->controller->stdout("\n");
            } else {
                $this->controller->stderr("{$srcDirPath} is not a directory!\n", Console::FG_RED);
            }
        }

        return $res;
    }

} 