<?php
/**
 * DeploYii - RunController
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\commands;

use app\lib\BaseConsoleController;
use app\lib\Log;
use app\lib\TaskRunner;
use yii\console\Exception;


/**
 * Run the build; if no target is specified, the "default" one will be ran.
 *
 * @author Giovanni Derks
 * @since  0.1
 */
class RunController extends BaseConsoleController
{

    /**
     * This command echoes what you have entered as the message.
     *
     * @param string $workspace Path to the project workspace
     * @param string $target    The target to be ran; if not specified "default" will be used
     *
     * @return int The exit code
     */
    public function actionIndex($workspace, $target = '')
    {

        $this->workspace = realpath($workspace);
        $buildFile = $this->workspace.DIRECTORY_SEPARATOR
            .$this->getScriptFolder().DIRECTORY_SEPARATOR.'build.php';

        $taskRunner = new TaskRunner($this, $buildFile);
        $exitCode = $taskRunner->run($target);

        $this->stdout("\n");

        return $exitCode;
    }

}
