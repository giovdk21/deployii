<?php
/**
 * DeploYii - RmdirCommand
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

class RmdirCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {

        $taskRunner = $this->taskRunner;

        $path = (!empty($cmdParams[0]) ? $taskRunner->parsePath($cmdParams[0]) : '');

        if (empty($path)) {
            throw new Exception('rmdir: Path cannot be empty');
        }

        $this->controller->stdout('Removing directory: ' . $path);

        if (!$this->controller->dryRun) {
            FileHelper::removeDirectory($path);
        } else {
            $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $this->controller->stdout("\n");
        return true;
    }

} 