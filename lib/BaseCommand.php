<?php
/**
 * DeploYii - BaseCommand
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;


class BaseCommand
{

    /** @var TaskRunner the TaskRunner instance */
    protected $taskRunner;

    /** @var BaseConsoleController the main controller; alias to $taskRunner->controller */
    protected $controller;

    /**
     * Initialise the Command
     *
     * @param TaskRunner $taskRunner
     */
    public function __construct($taskRunner)
    {
        $this->taskRunner = $taskRunner;
        $this->controller = $this->taskRunner->controller;
    }

    /**
     * @param array $cmdParams parameters passed to the command
     * @param array $params    build script parameters
     *
     * @return bool
     */
    public function run(& $cmdParams, & $params)
    {
        return true;
    }

} 