<?php
/**
 * DeploYii - ChmodCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\TaskRunner;
use yii\helpers\Console;
use Yii;

class ChmodCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {

        $res = true;
        $taskRunner = $this->taskRunner;

        $permList = (!empty($cmdParams[0]) ? $cmdParams[0] : []);


        foreach ($permList as $mode => $pathList) {

            $mode = (is_string($mode) ? octdec((int)$mode) : $mode);

            foreach ($pathList as $path) {
                $path = $taskRunner->parsePath($path);

                if (file_exists($path)) {
                    $this->controller->stdout("Changing permissions of {$path} to ");
                    $this->controller->stdout('0'.decoct($mode), Console::FG_CYAN);

                    if (!$this->controller->dryRun) {
                        @chmod($path, $mode);
                    } else {
                        $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
                    }
                } else {
                    $this->controller->stderr("Not found: {$path}\n", Console::FG_RED);
                }

                $this->controller->stdout("\n");
            }
        }

        return $res;
    }

} 