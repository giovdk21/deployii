<?php
/**
 * DeploYii - SftpPutCommand
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

class SftpPutCommand extends BaseCommand
{

    /** @var  Net_SFTP */
    private $_connection;
    /** @var  SftpHelper */
    private $_sftpHelper;

    private $_connectionId = '';
    private $_destDir = '';
    private $_srcBaseDir = '';
    private $_overwrite = false;
    private $_options = [];

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        $controller = $this->controller;
        $taskRunner = $this->taskRunner;

        $res = true;
        $toBeUploaded = [];
        $this->_connectionId = (!empty($cmdParams[0]) ? $cmdParams[0] : $this->_connectionId);
        $srcList = (!empty($cmdParams[1]) ? $cmdParams[1] : []);
        $this->_destDir = (!empty($cmdParams[2]) ? $taskRunner->parsePath($cmdParams[2]) : '');
        $this->_srcBaseDir = (!empty($cmdParams[3]) ? $taskRunner->parsePath($cmdParams[3]) : '');
        $this->_overwrite = (!empty($cmdParams[4]) ? $cmdParams[4] : $this->_overwrite);
        $this->_options = (!empty($cmdParams[5]) ? $cmdParams[5] : $this->_options);


        if (empty($this->_connectionId) || empty($this->_destDir)) {
            Log::throwException('sftpPut: Please specify a valid connection id and directory');
        }

        if (empty($srcList) || empty($this->_srcBaseDir)) {
            Log::throwException('sftpPut: srcList and srcBaseDir cannot be empty');
        }

        /** @noinspection PhpUndefinedMethodInspection (provided by the SftpConnectReqs Behavior) */
        $connParams = $controller->getConnectionParams($this->_connectionId);
        $controller->stdout(" ".$this->_connectionId." ", $connParams['sftpLabelColor'], Console::FG_BLACK);
        $controller->stdout(' Uploading files to ');
        $controller->stdout($this->_destDir, Console::FG_CYAN);

        if ($controller->dryRun) {
            $controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        if (is_string($srcList) && $srcList === '*') {
            $list = glob($this->_srcBaseDir.DIRECTORY_SEPARATOR.'*');
            foreach ($list as $srcFullPath) {
                $srcRelPath = substr($srcFullPath, strlen($this->_srcBaseDir)+1);
                $toBeUploaded[$srcRelPath] = $srcFullPath;
            }
        } else {
            // if $srcList is specified but it is a string, we convert it to an array
            if (!empty($srcList) && is_string($srcList)) {
                $srcList = explode(',', $srcList);
            }

            foreach ($srcList as $srcPath) {
                $parsedPath = $taskRunner->parseStringParams(trim($srcPath));
                $toBeUploaded[$parsedPath] = $this->_srcBaseDir.DIRECTORY_SEPARATOR.$parsedPath;
            }
        }

        if (!$controller->dryRun) {
            // the getConnection method is provided by the SftpConnectReqs Behavior
            /** @noinspection PhpUndefinedMethodInspection */
            $this->_connection = $controller->getConnection($this->_connectionId);
            $this->_sftpHelper = new SftpHelper($this->_connectionId, $this->_connection, $connParams);

            if (!is_dir($this->_srcBaseDir) || !$this->_sftpHelper->isDir($this->_destDir)) {
                $this->controller->stdout("\n");
                Log::throwException('sftpPut: Base and destination have to be directories');
            }
        }

        foreach ($toBeUploaded as $srcRelPath => $srcFullPath) {

            if (file_exists($srcFullPath)) {
                $this->_put($srcFullPath, $srcRelPath);
            } else {
                $this->controller->stdout("\n");
                $this->controller->stderr("{$srcFullPath} does not exists!\n", Console::FG_RED);
            }
        }

        if (!$controller->dryRun) {
            $this->_sftpHelper->flushCache();
        }

        $controller->stdout("\n");
        return $res;
    }


    private function _put($srcFullPath, $srcRelPath) {

        $destRelPath = $this->_destDir.'/'.$srcRelPath;

        $this->controller->stdout("\n - ".$srcRelPath."\t => ".$destRelPath);

        if (!$this->controller->dryRun) {

            if (is_dir($srcFullPath)) {
                $files = FileHelper::findFiles($srcFullPath, $this->_options);

                foreach ($files as $foundPath) {
                    $relativePath = substr($foundPath, strlen($this->_srcBaseDir)+1);

                    $this->_sftpHelper->mkdir($destRelPath);
                    $this->_put($foundPath, $relativePath, $this->_srcBaseDir, $this->_destDir, $this->_options);
                }
            } elseif (FileHelper::filterPath($srcFullPath, $this->_options)) {
                $this->_sftpHelper->mkdir(dirname($destRelPath), -1, true);

                if ($this->_overwrite || !$this->_sftpHelper->fileExists($destRelPath)) {

                    $res = $this->_sftpHelper->put($destRelPath, $srcFullPath);
                    if (!$res) {
                        Log::logger()->addError(
                            'sftpPut: error uploading file {from} to {to}',
                            ['from' => $srcFullPath, 'to' => $destRelPath]
                        );
                    }
                } else {
                    $this->controller->stdout(' [skipped]', Console::FG_PURPLE);
                }
            }

        } else {
            $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

    }

} 