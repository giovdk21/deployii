<?php
/**
 * DeploYii - CompressCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\Log;
use app\lib\TaskRunner;
use ArrayIterator;
use yii\helpers\Console;
use Yii;
use yii\helpers\FileHelper;

class CompressCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public static function run(& $cmdParams, & $params)
    {

        $res = true;
        $toBeAdded = [];
        $baseDir = (!empty($cmdParams[0]) ? TaskRunner::parsePath($cmdParams[0]) : '');
        $destFile = (!empty($cmdParams[1]) ? TaskRunner::parsePath($cmdParams[1]) : '');
        $fileList = (!empty($cmdParams[2]) ? $cmdParams[2] : []);
        $format = (!empty($cmdParams[3]) ? strtolower($cmdParams[3]) : 'gz');
        $options = (!empty($cmdParams[4]) ? $cmdParams[4] : []);

        // TODO: test different scenarios & options
        // TODO: allow append to archive (?)

        if (empty($baseDir) || !is_dir($baseDir)) {
            Log::throwException('compress: Base has to be a directory');
        }

        if (empty($destFile) || (file_exists($destFile) && is_dir($destFile))) {
            Log::throwException('compress: Destination has to be a file');
        }


        switch ($format) {

            case 'none':
                $extension = '.tar';
                $compression = \Phar::NONE;
                $doCompress = false;
                break;

            case 'gz':
                $extension = '.tar.gz';
                $compression = \Phar::GZ;
                $doCompress = true;
                break;

            case 'bz2':
                $extension = '.tar.bz2';
                $compression = \Phar::BZ2;
                $doCompress = true;
                break;

            default:
                $extension = '';
                $compression = false;
                $doCompress = false;
                Log::throwException('compress: Invalid format specified: '.$format);
                break;
        }


        // if fileList is specified but it is a string, we convert it to an array
        if (!empty($fileList) && is_string($fileList)) {
            $fileList = explode(',', $fileList);
        }

        if (empty($fileList) || !is_array($fileList)) {
            // if the $fileList array is empty we populate it with the baseDir
            $toBeAdded = ['./'.basename($baseDir) => $baseDir];
        } else {
            foreach ($fileList as $relPath) {
                $toBeAdded[$relPath] = $baseDir.DIRECTORY_SEPARATOR
                    .TaskRunner::parseStringParams(trim($relPath));
            }
        }


        TaskRunner::$controller->stdout("Creating archive: ");
        TaskRunner::$controller->stdout($destFile, Console::FG_BLUE);
        $archive = null;
        $destDir = dirname($destFile);
        $destBaseFile = $destDir.DIRECTORY_SEPARATOR.basename($destFile, '.tar');

        if (!TaskRunner::$controller->dryRun) {
            if (!is_dir($destDir)) {
                FileHelper::createDirectory($destDir);
            }
            @unlink($destFile);
            @unlink($destBaseFile);
            try {
                $archive = new \PharData($destFile);
            } catch (\Exception $e) {
                Log::throwException($e->getMessage());
            }
        } else {
            TaskRunner::$controller->stdout(' [dry run]', Console::FG_YELLOW);
        }
        TaskRunner::$controller->stdout("\n");


        TaskRunner::$controller->stdout("Adding to archive: ");
        foreach ($toBeAdded as $srcRelPath => $srcFullPath) {

            if (file_exists($srcFullPath)) {

                TaskRunner::$controller->stdout("\n - ".$srcFullPath);

                if (!TaskRunner::$controller->dryRun) {

                    if (is_dir($srcFullPath)) {
                        $files = FileHelper::findFiles($srcFullPath, $options);
                        $archive->buildFromIterator(
                            new ArrayIterator($files),
                            $srcFullPath
                        );
                    } else {
                        $archive->addFile($srcFullPath, $srcRelPath);
                    }

                } else {
                    TaskRunner::$controller->stdout(' [dry run]', Console::FG_YELLOW);
                }
            } else {
                TaskRunner::$controller->stderr("{$srcFullPath} is not a directory!\n", Console::FG_RED);
            }
        }
        TaskRunner::$controller->stdout("\n");

        if ($doCompress) {

            TaskRunner::$controller->stdout("Compressing archive: ");
            TaskRunner::$controller->stdout($destBaseFile.$extension, Console::FG_CYAN);

            if (!TaskRunner::$controller->dryRun) {
                @unlink($destBaseFile.$extension);
                try {
                    $archive->compress($compression, $extension);
                } catch (\Exception $e) {
                    Log::throwException($e->getMessage());
                }
                @unlink($destFile);
            } else {
                TaskRunner::$controller->stdout(' [dry run]', Console::FG_YELLOW);
            }
            TaskRunner::$controller->stdout("\n");
        }

        return $res;
    }

} 