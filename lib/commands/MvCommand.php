<?php
/**
 * DeploYii - MvCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use yii\base\ErrorException;
use yii\console\Exception;
use yii\helpers\Console;
use Yii;
use yii\helpers\FileHelper;

class MvCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {

        $res = true;
        $taskRunner = $this->taskRunner;

        $pathFrom = (!empty($cmdParams[0]) ? $taskRunner->parsePath($cmdParams[0]) : '');
        $pathTo = (!empty($cmdParams[1]) ? $taskRunner->parsePath($cmdParams[1]) : '');
        $overwrite = (!empty($cmdParams[2]) ? $cmdParams[2] : false);

        if (empty($pathFrom) || empty($pathTo)) {
            throw new Exception('mv: Origin and destination cannot be empty');
        }

        if (!file_exists($pathFrom)) {
            $this->controller->stderr("Not found: {$pathFrom}\n", Console::FG_RED);
        } else {

            $insidePathTo = FileHelper::normalizePath($pathTo).DIRECTORY_SEPARATOR.basename($pathFrom);

            if (is_file($pathFrom) && is_dir($pathTo)) {
                $pathTo = $insidePathTo;
            }

            $this->controller->stdout(
                "Move (overwrite: ".($overwrite ? 'yes' : 'no').") \n ".$pathFrom." to \n ".$pathTo
            );

            if (!$this->controller->dryRun) {

                if (!$overwrite && is_dir($pathFrom) && is_dir($pathTo)) {

                    if (!file_exists($insidePathTo)) {
                        // not overwriting; copy the source directory into the destination folder:
                        $res = rename($pathFrom, $insidePathTo);
                    } else {
                        $this->controller->stdout("\n");
                        $this->controller->warn(
                            "Destination directory {$insidePathTo} already exists; not overwriting"
                        );
                    }

                } elseif (!$overwrite && is_file($pathFrom) && is_file($pathTo)) {
                    $this->controller->stdout("\n");
                    $this->controller->warn("Destination file {$pathTo} already exists; not overwriting");
                } elseif (is_dir($pathFrom) && is_file($pathTo)) {
                    $this->controller->stdout("\n");
                    $this->controller->stderr("Trying to move a directory to a file: {$pathTo}", Console::FG_RED);
                } elseif (
                    !file_exists($pathTo)
                    || ($overwrite && is_dir($pathFrom) && is_dir($pathTo))
                    || ($overwrite && is_file($pathFrom) && is_file($pathTo))
                ) {
                    // if destination exists, overwrite it with the source file/dir
                    // note: if pathTo is a directory, it has to be empty in order to be overwritten
                    try {
                        $res = rename($pathFrom, $pathTo);
                    } catch (ErrorException $e) {
                        $this->controller->stdout("\n");
                        $this->controller->warn($e->getMessage());
                    }
                }

            } else {
                $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
            }

            $this->controller->stdout("\n");
        }

        return $res;
    }

} 