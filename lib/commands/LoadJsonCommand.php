<?php
/**
 * DeploYii - LoadJsonCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use yii\console\Exception;
use Yii;
use yii\helpers\Json;

class LoadJsonCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {

        $res = true;
        $taskRunner = $this->taskRunner;

        $filename = (!empty($cmdParams[0]) ? $taskRunner->parsePath($cmdParams[0]) : '');
        $prefix = (!empty($cmdParams[1]) ? $cmdParams[1] : '');
        $defaultValues = (!empty($cmdParams[2]) ? $cmdParams[2] : []);
        $flattenMethod = (!empty($cmdParams[3]) ? $cmdParams[3] : 'dot');

        if (empty($filename)) {
            throw new Exception('Please specify the path of the file you want to load');
        }

        $this->controller->stdout("Loading json file: \n  " . $filename);

        // initialise the parameters with the default values
        $defaultValues = $taskRunner->flattenArray($defaultValues, $flattenMethod, $prefix);
        foreach ($defaultValues as $key => $value) {
            $params[$key] = $value;
        }

        if (file_exists($filename)) {
            $data = Json::decode(file_get_contents($filename));

            if (is_array($data)) {

                $data = $taskRunner->flattenArray($data, $flattenMethod, $prefix);

                // override the parameters with the loaded values
                foreach ($data as $key => $value) {
                    $params[$key] = $value;
                }
            } else {
                $res = false; // invalid content
            }
        }

        $this->controller->stdout("\n");
        return $res;
    }

} 