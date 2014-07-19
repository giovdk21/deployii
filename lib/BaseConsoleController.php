<?php
/**
 * DeploYii - BaseConsoleController
 *
 * @link https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;

use yii\console\Controller;
use yii\helpers\Console;

class BaseConsoleController extends Controller {


    /**
     * @var bool Do not perform any real action.
     */
    public $dryRun = false;

    public $workspace;

    public $extraOptions = [];
    private $_extraParams = [];
    private $_providedOptions = [];

    private static $_log = [];

    public function init() {
        parent::init();

        $this->stdout(" Welcome to DeploYii ");
        $this->stdout(DEPLOYII_VERSION, Console::FG_YELLOW);
        $this->stdout("\n");
        if (!DEPLOYII_STABLE) {
            $this->stdout("Note: ", Console::FG_PURPLE);
            $this->stdout("this version is not ready for production! Use it only for learning / testing purposes.");
            $this->stdout("\n");
        }
    }

    /**
     * Extending runAction to handle extra params loaded dynamically
     * by (task) commands requirements
     *
     * @inheritdoc
     */
    public function runAction($id, $params = []) {

        if (!empty($params)) {
            $options = $this->options($id);
            foreach ($params as $name => $value) {
                if (!is_int($name)) {

                    if ($value !== '') {
                        if (!isset($this->_providedOptions[$name])) {
                            $this->_providedOptions[$name] = 1;
                        }
                        else {
                            $this->_providedOptions[$name]++;
                        }
                    }

                    if (!in_array($name, $options, true)) {
                        $this->_extraParams[$name] = $value;
                        unset($params[$name]);
                    }
                }
            }
        }

        parent::runAction($id, $params);
    }

    public function options($actionId) {

        $options = parent::options($actionId);

        $options[] = 'dryRun';

        return $options;
    }

    /**
     * @return array
     */
    public function getExtraParams() {
        return $this->_extraParams;
    }

    /**
     * @return array
     */
    public function getProvidedOptions() {
        return $this->_providedOptions;
    }

    /**
     * Does what runAction does for normal parameters but for the extra ones.
     *
     * Extra parameters are loaded dynamically by commands requirements and are initialised
     * after every requirement has been processed from the TaskRunner::init() method.
     */
    public function initExtraParams() {
        $params = $this->getExtraParams();

        foreach ($params as $name => $value) {
            if (in_array($name, $this->extraOptions, true) && $value !== '') {
                $default = $this->$name;
                $this->$name = is_array($default) ? preg_split('/\s*,\s*/', $value) : $value;
            }
        }

    }

    /**
     * Return the path of the folder containing all the scripts needed to init, build and/or
     * deploy the current project.
     *
     * @return string Path of the script folder relative to $this->workspace
     */
    public function getScriptFolder() {
        return 'deployii';
    }

    /**
     * Get the requested parameter value from the build parameters
     * or from the controller properties (CLI options)
     *
     * @param string $name
     * @param array  $buildParams
     *
     * @return mixed|null
     */
    public function getParamVal($name, & $buildParams) {
        $res = null;

        if (isset($buildParams[$name])) {
            $res = $buildParams[$name];
        }
        elseif ($this->hasProperty($name)) {
            $res = $this->$name;
        }

        return $res;
    }


} 