<?php
/**
 * DeploYii - SaveLogCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\Log;
use app\lib\TaskRunner;
use Monolog\Formatter\HtmlFormatter;
use Monolog\Handler\StreamHandler;
use yii\helpers\Console;
use Yii;

class SaveLogCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public static function run(& $cmdParams, & $params)
    {

        $res = true;
        $filename = (!empty($cmdParams[0]) ? TaskRunner::parsePath($cmdParams[0]) : '');
        $format = (!empty($cmdParams[1]) ? $cmdParams[1] : 'plain');
        $append = (!empty($cmdParams[2]) ? $cmdParams[2] : false);

        if (empty($filename)) {
            Log::throwException('Please specify the path of the file you want to save to');
        }
        elseif (!$append && file_exists($filename)) {
            unlink($filename);
        }

        switch ($format) {
            case 'plain':
                $formatter = null; // use the default formatter
                break;

            case 'html':
                $formatter = new HtmlFormatter();
                break;

            default:
                $formatter = false;
                TaskRunner::$controller->stderr("Invalid log format: {$format}\n", Console::FG_RED);
                break;
        }

        TaskRunner::$controller->stdout("Saving log file: \n  " . $filename);

        if (!TaskRunner::$controller->dryRun) {

            $logHandler = new StreamHandler($filename);

            if (!empty($formatter)) {
                $logHandler->setFormatter($formatter);
            }

            Log::$deployiiHandler->sendToHandler($logHandler);
        } else {
            TaskRunner::$controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        TaskRunner::$controller->stdout("\n");
        return $res;
    }

} 