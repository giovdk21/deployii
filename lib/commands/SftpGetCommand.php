<?php
/**
 * DeploYii - SftpGetCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\Log;
use app\lib\SftpHelper;
use yii\helpers\Console;
use Yii;
use Net_SFTP;
use yii\helpers\FileHelper;

class SftpGetCommand extends BaseCommand
{

    /** @var  Net_SFTP */
    private $_connection;
    /** @var  SftpHelper */
    private $_sftpHelper;

    private $_connectionId = '';
    private $_destPath = '';
    private $_overwrite = false;

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        $controller = $this->controller;
        $taskRunner = $this->taskRunner;

        $res = true;
        $this->_connectionId = (!empty($cmdParams[0]) ? $cmdParams[0] : $this->_connectionId);
        $remotePath = (!empty($cmdParams[1]) ? $cmdParams[1] : []);
        $this->_destPath = (!empty($cmdParams[2]) ? $taskRunner->parsePath($cmdParams[2]) : '');
        $this->_overwrite = (!empty($cmdParams[3]) ? $cmdParams[3] : $this->_overwrite);
        // TODO: exclude / filter (?)


        if (empty($this->_connectionId) || empty($this->_destPath)) {
            Log::throwException('sftpGet: Please specify a valid connection id and destination directory');
        }

        if (empty($remotePath)) {
            Log::throwException('sftpGet: remotePath cannot be empty');
        }

        /** @noinspection PhpUndefinedMethodInspection (provided by the SftpConnectReqs Behavior) */
        $connParams = $controller->getConnectionParams($this->_connectionId);
        $controller->stdout(" ".$this->_connectionId." ", $connParams['sftpLabelColor'], Console::FG_BLACK);
        $controller->stdout(' Downloading to: ');
        $controller->stdout($this->_destPath, Console::FG_CYAN);

        if (!$controller->dryRun) {
            // the getConnection method is provided by the SftpConnectReqs Behavior
            /** @noinspection PhpUndefinedMethodInspection */
            $this->_connection = $controller->getConnection($this->_connectionId);
            $this->_sftpHelper = new SftpHelper($this->_connectionId, $this->_connection, $connParams);
        }

        $this->_get($remotePath);

        $controller->stdout("\n");
        return $res;
    }


    private function _get($remotePath, $destRelPathArr = []) {

        // Remove the first element from the $destRelPathArr since it corresponds to the destination path
        $destSubFoldersArr = $destRelPathArr;
        array_shift($destSubFoldersArr);
        $destRelPath = implode(DIRECTORY_SEPARATOR, $destSubFoldersArr);

        $localFolderName = basename($this->_destPath);
        $localFolderName .= (!empty($destRelPath) ? DIRECTORY_SEPARATOR.$destRelPath : '');

        $destPath = $this->_destPath;
        $destPath .= (!empty($destSubFoldersArr) ? DIRECTORY_SEPARATOR.$destRelPath : '');

        $this->controller->stdout("\n - {$localFolderName}\t <= {$remotePath}");

        if (!$this->controller->dryRun) {

            if ($this->_sftpHelper->isDir($remotePath)) {

                if (!is_dir($this->_destPath)) {
                    $this->controller->stdout("\n");
                    Log::throwException('sftpGet: remotePath is a directory, therefore destination has to be a directory too');
                }

                $list = $this->_sftpHelper->nlist($remotePath);
                $remoteDirName = basename($remotePath);
                $newSubDir = $destPath.DIRECTORY_SEPARATOR.$remoteDirName;
                $destRelPathArr[] = $remoteDirName;

                // Creating the sub-directory before recursion to allow creation of empty directories
                // (excluding the first level as it corresponds to the destination path)
                if (count($destRelPathArr) > 1 && !is_dir($newSubDir)) {
                    FileHelper::createDirectory($newSubDir);
                }

                foreach ($list as $item) {
                    if ($item !== '.' && $item !== '..') {
                        $this->_get($remotePath.'/'.$item, $destRelPathArr);
                    }
                }

            } elseif (!empty($destSubFoldersArr) || $this->_sftpHelper->fileExists($remotePath)) {
                // if $destSubFoldersArr is not empty it means that we've got the list of files
                // from the nlist command on the remote directory; therefore we don't need to check
                // if the file exists

                $destFile = $destPath;
                if (is_dir($destFile)) {
                    $destFile = $destPath.DIRECTORY_SEPARATOR.basename($remotePath);
                }

                if ($this->_overwrite || !file_exists($destFile)) {
                    $this->_sftpHelper->get($remotePath, $destFile);
                } else {
                    $this->controller->stdout(' [skipped]', Console::FG_PURPLE);
                }
            } else {
                $this->controller->stdout("\n");
                $this->controller->warn('Not found: '.$remotePath);
            }

        } else {
            $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

    }

} 