<?php
/**
 * DeploYii - RunController
 *
 * @link https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\commands;

use app\lib\BaseConsoleController;
use app\lib\TaskRunner;


/**
 * Run the build; if no target is specified, the "default" one will be ran.
 *
 * @author Giovanni Derks
 * @since 0.1
 */
class RunController extends BaseConsoleController
{

    /**
     * This command echoes what you have entered as the message.
     *
     * @param string     $workspace
     * @param string     $target
     *
     * @return int
     */
    public function actionIndex($workspace, $target = '')
    {

        $this->workspace = realpath($workspace);
        $buildFile = $this->workspace . '/' . $this->getScriptFolder() . '/build.php'; // TODO: parametrise deployii folder name / path (relative to workspace)

        TaskRunner::init($this, $buildFile);
        $exitCode = TaskRunner::run($this, $target);

        $this->stdout("\n");

        return $exitCode;
    }


}
