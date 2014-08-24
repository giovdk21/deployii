<?php
/**
 * DeploYii - MkdirCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use yii\console\Exception;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use Yii;

class MkdirCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {

        $res = true;
        $taskRunner = $this->taskRunner;

        $path = (!empty($cmdParams[0]) ? $taskRunner->parsePath($cmdParams[0]) : '');

        if (empty($path)) {
            throw new Exception('mkdir: Path cannot be empty');
        }

        $this->controller->stdout('Creating directory: ' . $path);

        if (!$this->controller->dryRun) {
            $res = FileHelper::createDirectory($path);
        } else {
            $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $this->controller->stdout("\n");
        return $res;
    }

} 