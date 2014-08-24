<?php
/**
 * DeploYii - InitController
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\commands;

use app\lib\BaseConsoleController;
use app\lib\Log;
use app\lib\Shell;
use app\lib\TaskRunner;
use yii\helpers\FileHelper;


/**
 * Initialise a new build.php script from the build.tpl.php template file
 * (located in the DeploYii home directory).
 *
 * @author Giovanni Derks
 * @since  0.3
 */
class InitController extends BaseConsoleController
{

    /**
     * @var bool Whether run or not the build after initialising it.
     */
    public $run = false;

    /**
     * @var bool If true, the build config.php will be created from the template in the DeploYii home
     */
    public $createConfig = false;

    /**
     * @param string $workspace Path to the project workspace
     *
     * @return int The exit code
     */
    public function actionIndex($workspace)
    {
        $exitCode = 0;

        $this->_checkDirectory($workspace);
        $this->workspace = realpath($workspace);

        $scriptsDir = $this->workspace.DIRECTORY_SEPARATOR.$this->getScriptFolder();
        $this->_checkDirectory($scriptsDir);

        $buildFile = $scriptsDir.DIRECTORY_SEPARATOR.'build.php';
        if (file_exists($buildFile)) {
            Log::throwException('Build script already exists in the given workspace: '.$buildFile);
        } else {

            $home = Shell::getHomeDir();
            $srcFile = $home.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'build.tpl.php';
            if (file_exists($srcFile)) {

                $this->stdout('- ');
                $this->logStdout('Generating build script: '.$buildFile."\n");

                if (!$this->dryRun) {
                    $template = file_get_contents($srcFile);
                    $template = str_replace('{{deployiiVersion}}', DEPLOYII_VERSION, $template);

                    // Save build file
                    file_put_contents($buildFile, $template);

                    // Copy gitignore
                    copy(
                        $home.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'gitignore.tpl',
                        $scriptsDir.DIRECTORY_SEPARATOR.'.gitignore'
                    );

                    // Copy config.php (if needed)
                    if ($this->interactive) {
                        $createConf = $this->confirm(
                            'Do you need to create the build configuration file?',
                            $this->createConfig
                        );
                    } else {
                        $createConf = $this->createConfig;
                    }
                    if ($createConf) {
                        $configFile = $home.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'config.tpl.php';
                        $buildConfigFile = $scriptsDir.DIRECTORY_SEPARATOR.'config.php';

                        if (!file_exists($buildConfigFile)) {
                            $this->logStdout('Generating build config: '.$buildConfigFile."\n");
                            copy($configFile, $buildConfigFile);
                        } else {
                            Log::throwException(
                                'Build configuration already exists in the given workspace: '.$buildConfigFile
                            );
                        }
                    }

                    $run = $this->confirm('Do you want to run the build script?', $this->run);
                    if ($run) {
                        $taskRunner = new TaskRunner($this, $buildFile);
                        $exitCode = $taskRunner->run();
                    }
                }
            }
        }

        $this->stdout("\n");
        return $exitCode;
    }

    /**
     * @param string $path
     */
    private function _checkDirectory($path)
    {
        $path = rtrim($path, '/\\');
        if (!is_dir($path) && !file_exists($path)) {
            if (!$this->dryRun) {
                FileHelper::createDirectory($path);
            }
        } elseif (!is_dir($path) && file_exists($path)) {
            Log::throwException('Invalid directory: '.$path);
        }
    }


    /**
     * @inheritdoc
     */
    public function options($actionId = '')
    {

        $options = parent::options($actionId);

        $options[] = 'run';
        $options[] = 'createConfig';

        return $options;
    }

}
