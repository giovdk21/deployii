<?php
/**
 * DeploYii - SaveAsJsonCommand
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
use yii\helpers\Json;

class SaveAsJsonCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {

        $res = true;
        $taskRunner = $this->taskRunner;

        $filename = (!empty($cmdParams[0]) ? $taskRunner->parsePath($cmdParams[0]) : '');
        $data = (!empty($cmdParams[1]) ? $cmdParams[1] : []);

        if (empty($filename)) {
            throw new Exception('Please specify the path of the file you want to save to');
        }

        $this->controller->stdout("Saving json file: \n  " . $filename);

        if (!$this->controller->dryRun) {
            $res = file_put_contents($filename, Json::encode($data));
        } else {
            $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $this->controller->stdout("\n");
        return $res;
    }

} 