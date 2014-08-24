<?php
/**
 * DeploYii - CopyCommand
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

class CopyCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {

        $res = true;
        $taskRunner = $this->taskRunner;

        $fileFrom = (!empty($cmdParams[0]) ? $taskRunner->parsePath($cmdParams[0]) : '');
        $fileTo = (!empty($cmdParams[1]) ? $taskRunner->parsePath($cmdParams[1]) : '');

        if (empty($fileFrom) || empty($fileTo)) {
            throw new Exception('copy: Origin and destination cannot be empty');
        }

        if (is_dir($fileTo)) {
            $fileTo = FileHelper::normalizePath($fileTo).DIRECTORY_SEPARATOR.basename($fileFrom);
        }

        $this->controller->stdout("Copy file: \n  ".$fileFrom." to \n  ".$fileTo);

        if (!$this->controller->dryRun) {
            $res = copy($fileFrom, $fileTo);
        } else {
            $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $this->controller->stdout("\n");
        return $res;
    }

} 