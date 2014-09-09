<?php
/**
 * DeploYii - GitCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use app\lib\BaseCommand;
use app\lib\Log;
use GitWrapper\GitException;
use GitWrapper\GitWrapper;
use yii\console\Exception;
use Yii;
use yii\helpers\Console;

class GitCommand extends BaseCommand
{

    /**
     * @inheritdoc
     */
    public function run(& $cmdParams, & $params)
    {
        $res = false;
        $taskRunner = $this->taskRunner;

        $gitCmd = (!empty($cmdParams[0]) ? $taskRunner->parseStringAliases($cmdParams[0]) : '');

        if (empty($gitCmd)) {
            throw new Exception('git: Command cannot be empty');
        }

        $this->controller->stdout('Running git ' . $gitCmd . '...');

        if (!$this->controller->dryRun) {

            $wrapper = false;
            try {
                $wrapper = new GitWrapper();
            } catch (GitException $e) {
                Log::throwException($e->getMessage());
            }

            if ($wrapper) {

                switch ($gitCmd) {
                    case 'clone':
                        $repo = (!empty($cmdParams[1]) ? $cmdParams[1] : '');
                        $destDir = (!empty($cmdParams[2]) ? $taskRunner->parsePath($cmdParams[2]) : '');

                        if (empty($repo) || empty($destDir)) {
                            throw new Exception('git: repository and destination directory cannot be empty');
                        }

                        $gitOptions = (!empty($cmdParams[3]) ? $cmdParams[3] : []);
                        $res = $wrapper->cloneRepository($repo, $destDir, $gitOptions);
                        break;

                    default:
                        $this->controller->stdout("\n");
                        $this->controller->stderr("Command not supported: {$gitCmd}", Console::FG_RED);
                        break;
                }
            }
        } else {
            $this->controller->stdout(' [dry run]', Console::FG_YELLOW);
        }

        $this->controller->stdout("\n");
        return !empty($res);
    }

} 