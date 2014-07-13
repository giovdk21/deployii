<?php
/**
 * DeploYii - FetchController
 *
 * @link https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\commands;

use app\lib\BaseConsoleController;
use app\lib\TaskRunner;
use yii\helpers\Console;
use GitWrapper\GitWrapper;
use GitWrapper\GitException;


/**
 * Fetch the requested project from git and run the build.
 *
 * @author Giovanni Derks
 * @since 0.1
 */
class FetchController extends BaseConsoleController
{

    /**
     * @var bool Whether run or not the build after fetching the sources.
     */
    public $run = true;

    /**
     * @var string The target to run after fetching the sources.
     *             By default, the "default" target will be ran.
     */
    public $target = '';

    /**
     * @param $projectId
     *
     * @return int
     */
    public function actionIndex($projectId) {
        $exitCode = 0;

        // TODO: read info from the database
        $projectInfo = (object)require(__DIR__.'/../projects_tmp/'.$projectId.'.php');
        // ------------------------



        $this->workspace = __DIR__.'/../workspace/'.$projectInfo->id."_".time();

        $gitClone = false;
        try {
            $wrapper = new GitWrapper();
            $gitOptions = [];

            if (!empty($projectInfo->branch)) {
                $gitOptions['branch'] = $projectInfo->branch;
            }

            // TODO: refactor using different variable for clone directory (workspace should include the optional rootFolder)
            $gitClone = $wrapper->cloneRepository($projectInfo->repo, $this->workspace, $gitOptions);
        }
        catch (GitException $e) {
            $this->stderr($e->getMessage(), Console::FG_RED);
            $exitCode = 1;
        }

        if (!empty($projectInfo->rootFolder)) {
            $this->workspace .= '/' . $projectInfo->rootFolder;
        }

        if ($gitClone && $this->run) {

                $buildFile = $this->workspace.'/'.$this->getScriptFolder().'/build.php'; // TODO: parametrise deployii folder name / path (relative to workspace)

            if (file_exists($buildFile)) {
                TaskRunner::init($this, $buildFile);
                $exitCode = TaskRunner::run($this, $this->target);
            }
            else {
                $this->stderr("Build file not found: ".$buildFile, Console::FG_RED);
                $exitCode = 1;
            }

        }

        $this->stdout("\n");
        return $exitCode;
    }


    public function options($actionId = '') {

        $options = parent::options($actionId);

        $options[] = 'run';
        $options[] = 'target';

        return $options;
    }

}
