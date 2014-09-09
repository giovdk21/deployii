<?php
/**
 * DeploYii - GitReqs
 *
 * This behavior will be attached to $this->controller when the
 * associated command is specified as a requirement.
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib\commands;

use yii\base\Behavior;

class GitReqs extends Behavior
{

    /**
     * @return array the list of command options => default values
     */
    public static function getCommandOptions()
    {
        return [];
    }

    /**
     * This is ran on init() and should perform global requirements check;
     * see $this->taskRunner->_checkAllRequirements()
     *
     * @param array $buildParams build script parameters
     */
    public static function checkRequirements(& $buildParams)
    {
        // TODO: check that git is available on the system
    }

}