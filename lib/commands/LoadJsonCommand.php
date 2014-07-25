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
use app\lib\TaskRunner;
use yii\console\Exception;
use Yii;
use yii\helpers\Json;

class LoadJsonCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public static function run(& $cmdParams, & $params)
    {

        $res = true;
        $filename = (!empty($cmdParams[0]) ? TaskRunner::parsePath($cmdParams[0]) : '');
        $prefix = (!empty($cmdParams[1]) ? $cmdParams[1] : '');
        $defaultValues = (!empty($cmdParams[2]) ? $cmdParams[2] : []);

        if (empty($filename)) {
            throw new Exception('Please specify the path of the file you want to load');
        }

        TaskRunner::$controller->stdout("Loading json file: \n  " . $filename);

        $prefix = (!empty($prefix) ? $prefix . '_' : '');

        // initialise the parameters with the default values
        foreach ($defaultValues as $key => $value) {
            $params[$prefix . $key] = $value;
        }

        if (file_exists($filename)) {
            $data = Json::decode(file_get_contents($filename));

            if (is_array($data)) {
                // override the parameters with the loaded values
                foreach ($data as $key => $value) {
                    $params[$prefix . $key] = $value;
                }
            } else {
                $res = false; // invalid content
            }
        }

        TaskRunner::$controller->stdout("\n");
        return $res;
    }

} 