<?php
/**
 * DeploYii - SftpMvCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\SftpHelper;
use Net_SFTP;
use yii\base\ErrorException;
use yii\console\Exception;
use yii\helpers\Console;
use Yii;

class SftpMvCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {

        $res = true;
        $taskRunner = $this->taskRunner;

        $connectionId = (!empty($cmdParams[0]) ? $cmdParams[0] : '');
        $pathFrom = (!empty($cmdParams[1]) ? $taskRunner->parsePath($cmdParams[1]) : '');
        $pathTo = (!empty($cmdParams[2]) ? $taskRunner->parsePath($cmdParams[2]) : '');
        $overwrite = (!empty($cmdParams[3]) ? $cmdParams[3] : false);
        // TODO: check if overwrite is needed after implementing the FTP support

        if (empty($pathFrom) || empty($pathTo)) {
            throw new Exception('sftpMv: Origin and destination cannot be empty');
        }

        $this->controller->stdout(" ".$connectionId." ", Console::BG_BLUE, Console::FG_BLACK);
        $this->controller->stdout(
            " Move (overwrite: ".($overwrite ? 'yes' : 'no').") \n ".$pathFrom." to \t ".$pathTo
        );


        $insidePathTo = $pathTo.'/'.basename($pathFrom);

        if (!$this->controller->dryRun) {

            /** @noinspection PhpUndefinedMethodInspection */
            /** @var $connection Net_SFTP */
            $connection = $this->controller->getConnection($connectionId);
            $sftpHelper = new SftpHelper($connectionId, $connection);

            if (!$sftpHelper->fileExists($pathFrom)) {
                $this->controller->stdout("\n");
                $this->controller->stderr("Not found: {$pathFrom}", Console::FG_RED);
            } else {

                if ($sftpHelper->isFile($pathFrom) && $sftpHelper->isDir($pathTo)) {
                    $pathTo = $insidePathTo;
                }

                if (!$overwrite && $sftpHelper->isDir($pathFrom) && $sftpHelper->isDir($pathTo)) {

                    if (!$sftpHelper->fileExists($insidePathTo)) {
                        // not overwriting; copy the source directory into the destination folder:
                        $res = $connection->rename($pathFrom, $insidePathTo);
                    } else {
                        $this->controller->stdout("\n");
                        $this->controller->warn(
                            "Destination directory {$insidePathTo} already exists; not overwriting"
                        );
                    }

                } elseif (!$overwrite && $sftpHelper->isFile($pathFrom) && $sftpHelper->isFile($pathTo)) {
                    $this->controller->stdout("\n");
                    $this->controller->warn("Destination file {$pathTo} already exists; not overwriting");
                } elseif ($sftpHelper->isDir($pathFrom) && $sftpHelper->isFile($pathTo)) {
                    $this->controller->stdout("\n");
                    $this->controller->stderr("Trying to move a directory to a file: {$pathTo}", Console::FG_RED);
                } elseif (
                    !$sftpHelper->fileExists($pathTo)
                    || ($overwrite && $sftpHelper->isDir($pathFrom) && $sftpHelper->isDir($pathTo))
                    || ($overwrite && $sftpHelper->isFile($pathFrom) && $sftpHelper->isFile($pathTo))
                ) {
                    // if destination exists, overwrite it with the source file/dir
                    // note: if pathTo is a directory, it has to be empty in order to be overwritten
                    try {
                        $res = $connection->rename($pathFrom, $pathTo);
                    } catch (ErrorException $e) {
                        $this->controller->stdout("\n");
                        $this->controller->warn($e->getMessage());
                    }

                    if (!$res) {
                        // Note: using sftp, renaming a directory to another does not work even if destination is empty
                        $this->controller->stdout("\n");
                        $this->controller->stderr("Failed moving {$pathFrom} to {$pathTo}", Console::FG_RED);
                    }
                }
            }


            $sftpHelper->flushCache();
        } else {
            $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $this->controller->stdout("\n");
        return $res;
    }

} 