<?php
/**
 * DeploYii - RmCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use yii\console\Exception;
use Yii;
use yii\helpers\Console;

class RmCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {

        $taskRunner = $this->taskRunner;

        $filename = (!empty($cmdParams[0]) ? $taskRunner->parsePath($cmdParams[0]) : '');

        if (empty($filename)) {
            throw new Exception('rm: filename cannot be empty');
        }

        $this->controller->stdout('Removing file: ' . $filename);

        if (!$this->controller->dryRun) {
            @unlink($filename);
        } else {
            $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $this->controller->stdout("\n");
        return true;
    }

} 