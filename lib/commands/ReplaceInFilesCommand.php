<?php
/**
 * DeploYii - ReplaceInFilesCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\Log;
use yii\helpers\Console;
use Yii;
use yii\helpers\FileHelper;

class ReplaceInFilesCommand extends BaseCommand
{

    private $_replaceList = [];

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        // TODO: add log info
        $res = true;
        $taskRunner = $this->taskRunner;

        $toBeParsed = [];
        $fileList = (!empty($cmdParams[0]) ? $cmdParams[0] : []);
        $this->_replaceList = (!empty($cmdParams[1]) ? $cmdParams[1] : $this->_replaceList);
        $baseDir = (!empty($cmdParams[2]) ? $taskRunner->parsePath($cmdParams[2]) : '');
        $options = (!empty($cmdParams[3]) ? $cmdParams[3] : []);


        if (!empty($baseDir) && (!empty($baseDir) && !is_dir($baseDir))) {
            Log::throwException('replaceInFiles: baseDir has to be a directory');
        }

        // if $fileList is specified but it is a string, we convert it to an array
        if (!empty($fileList) && is_string($fileList)) {
            $fileList = explode(',', $fileList);
        }

        foreach ($fileList as $srcPath) {

            $parsedPath = $taskRunner->parseStringAliases(trim($srcPath));

            if (!empty($baseDir)) {
                $toBeParsed[$parsedPath] = $baseDir.DIRECTORY_SEPARATOR.$parsedPath;
            } else {
                $toBeParsed[] = $parsedPath;
            }
        }


        $this->controller->stdout("Replacing in files: ");
        foreach ($toBeParsed as $srcRelPath => $srcFullPath) {

            if (file_exists($srcFullPath)) {

                if (is_dir($srcFullPath)) {
                    $files = FileHelper::findFiles($srcFullPath, $options);

                    foreach ($files as $foundPath) {
                        $this->_replace($foundPath);
                    }
                } elseif (FileHelper::filterPath($srcFullPath, $options)) {
                    $this->_replace($srcFullPath);
                }


            } else {
                $this->controller->stderr("\n{$srcFullPath} does not exists!\n", Console::FG_RED);
            }
        }
        $this->controller->stdout("\n");

        return $res;
    }


    private function _replace($file)
    {
        $res = true;
        $taskRunner = $this->taskRunner;
        $this->controller->stdout("\n - ".$file);

        if (!$this->controller->dryRun) {

            $content = file_get_contents($file);

            foreach ($this->_replaceList as $replaceInfo) {

                $pattern = (isset($replaceInfo[0]) ? $replaceInfo[0] : '');
                $replacement = (isset($replaceInfo[1]) ? $taskRunner->parseStringParams($replaceInfo[1]) : '');
                $flags = (isset($replaceInfo[2]) ? $replaceInfo[2] : '');


                if (!empty($pattern)) {
                    $pattern = addcslashes($pattern, '/');

                    if (!is_callable($replacement)) {
                        $content = preg_replace('/'.$pattern.'/'.$flags, $replacement, $content);
                    } else {
                        $content = preg_replace_callback('/'.$pattern.'/'.$flags, $replacement, $content);
                    }
                }
            }

            $res = file_put_contents($file, $content);
        } else {
            $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        return $res;
    }

} 