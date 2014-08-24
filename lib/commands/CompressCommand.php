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

    const CMP_GZ = 'gz';
    const CMP_BZ2 = 'bz2';
    const CMP_NONE = 'none';

    /**
     * @inheritdoc
     */
    public static function run(& $cmdParams, & $params)
    {

        $res = true;
        $toBeAdded = [];
        $srcList = (!empty($cmdParams[0]) ? $cmdParams[0] : []);
        $destFile = (!empty($cmdParams[1]) ? TaskRunner::parsePath($cmdParams[1]) : '');
        $srcBaseDir = (!empty($cmdParams[2]) ? TaskRunner::parsePath($cmdParams[2]) : '');
        $format = (!empty($cmdParams[3]) ? strtolower($cmdParams[3]) : self::CMP_GZ);
        $options = (!empty($cmdParams[4]) ? $cmdParams[4] : []);


        if (!empty($srcBaseDir) && !is_dir($srcBaseDir)) {
            Log::throwException('compress: srcBaseDir has to be a directory');
        }

        if (empty($destFile) || (file_exists($destFile) && is_dir($destFile))) {
            Log::throwException('compress: Destination has to be a file');
        }


        switch ($format) {

            case self::CMP_NONE:
                $extension = '.tar';
                $compression = \Phar::NONE;
                $doCompress = false;
                break;

            case self::CMP_GZ:
                $extension = '.tar.gz';
                $compression = \Phar::GZ;
                $doCompress = true;
                break;

            case self::CMP_BZ2:
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


        // if $srcList is specified but it is a string, we convert it to an array
        if (!empty($srcList) && is_string($srcList)) {
            $srcList = explode(',', $srcList);
        }


        foreach ($srcList as $srcPath) {

            $parsedPath = TaskRunner::parseStringAliases(trim($srcPath));

            if (!empty($srcBaseDir)) {
                $toBeAdded[$parsedPath] = $srcBaseDir.DIRECTORY_SEPARATOR.$parsedPath;
            } else {
                $toBeAdded[] = $parsedPath;
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
                            !empty($srcBaseDir) ? $srcBaseDir : $srcFullPath
                        );
                    } elseif (FileHelper::filterPath($srcFullPath, $options)) {
                        $archive->addFile(
                            $srcFullPath,
                            !empty($srcBaseDir) ? $srcRelPath : basename($srcFullPath)
                        );
                    }

                } else {
                    TaskRunner::$controller->stdout(' [dry run]', Console::FG_YELLOW);
                }
            } else {
                TaskRunner::$controller->stderr("\n{$srcFullPath} does not exists!\n", Console::FG_RED);
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