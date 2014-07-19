<?php
/**
 * DeploYii - TaskRunner
 *
 * @link https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;

use yii\console\Exception;
use yii\helpers\Console;
use Yii;

class TaskRunner {

    private static $_buildScript = [];
    private static $_params = [];
    private static $_loadedRequirements = [];
    private static $_recipes = [];

    public static function init(BaseConsoleController $controller, $buildFile) {
        self::$_params = [];
        self::$_loadedRequirements = [];

        /** @noinspection PhpIncludeInspection */
        self::$_buildScript = require($buildFile);

        // Set default aliases
        self::_setAliases($controller);

        // Check script compatibility:
        if (!empty(self::$_buildScript['deployiiVersion'])) {
            VersionManager::checkBuildVersion(self::$_buildScript['deployiiVersion']);
        }
        else {
            throw new Exception('Please specify the "deployiiVersion" in your build script');
        }

        // Check that the user defined commands and recipes do not override the built-in ones:
        self::_checkUserScripts();

        // Load requirements and initialise the extra parameters:
        if (!empty(self::$_buildScript['require'])) {
            self::_loadRequirements($controller, self::$_buildScript['require']);
        }
        $controller->initExtraParams();


        // Load default parameters from build file:
        if (!empty(self::$_buildScript['params'])) {
            self::$_params = array_merge(self::$_params, self::$_buildScript['params']);
        }

        self::_setParamsFromOptions($controller);


        self::_checkAllRequirements($controller);
    }

    public static function run(BaseConsoleController $controller, $target = '') {
        $exitCode = 0;
        $target = (empty($target) ? 'default' : $target);

        if (empty(self::$_buildScript)) {
            throw new Exception('Empty script: init() not called or invalid build file');
        }

        if (isset(self::$_buildScript['targets'][$target])) { // run the selected target
            TaskRunner::_runTarget($controller, self::$_buildScript['targets'][$target], $target);
        }
        else {
            $controller->stderr('Target not found: '.$target, Console::FG_RED);
            $exitCode = 1;
        }

        return $exitCode;
    }

    private static function _runTarget(BaseConsoleController $controller, $targetScript, $targetName = '') {
        $controller->stdout("Running ".$targetName."...\n", Console::FG_GREEN, Console::BOLD);
        self::_process($controller, $targetScript);
    }

    private static function _loadRecipe(BaseConsoleController $controller, $recipeName, $userRecipe = false) {
        $recipeScript = false;

        $recipeClass = ucfirst($recipeName) . 'Recipe';

        if (!$userRecipe) {
            $recipesDir = __DIR__ . '/recipes';
        }
        else {
            $recipesDir = Yii::getAlias('@buildScripts/recipes');
        }

        $recipeFile = $recipesDir . '/' . $recipeClass . '.php';

        if (file_exists($recipeFile)) {
            /** @noinspection PhpIncludeInspection */
            $recipeScript = require($recipeFile);
        }
        elseif (!$userRecipe) {
            $recipeScript = self::_loadRecipe($controller, $recipeName, true);
        }

        // Load requirements and initialise the extra parameters:
        if (!empty($recipeScript['require'])) {
            self::_loadRequirements($controller, $recipeScript['require']);
        }

        self::$_recipes[$recipeName] = $recipeScript;
        return $recipeScript;
    }

    private static function _runRecipe(
        BaseConsoleController $controller, $recipeName, $recipeTarget=''
    ) {
        $recipeTarget = (empty($recipeTarget) ? 'default' : $recipeTarget);

        if (
            empty($recipeName)
            || empty(self::$_recipes[$recipeName])
            || !is_array(self::$_recipes[$recipeName])
            || !isset(self::$_recipes[$recipeName]['targets'])
            || empty(self::$_recipes[$recipeName]['targets'][$recipeTarget])
        ) {
            throw new Exception("Invalid recipe / target: {$recipeName} [{$recipeTarget}]");
        }

        $recipeScript = self::$_recipes[$recipeName]['targets'][$recipeTarget];

        $controller->stdout(
            "Running recipe: {$recipeName} [{$recipeTarget}]...\n",
            Console::FG_GREEN, Console::BOLD
        );
        self::_process($controller, $recipeScript);
    }

    private static function _process(BaseConsoleController $controller, $script) {

        $params = & self::$_params;

        foreach ($script as $functionParams) {

            $cmdName = array_shift($functionParams);
            $function = null;

            switch ($cmdName) {

                case 'target':
                    $targetName = (!empty($functionParams[0]) ? $functionParams[0] : '');
                    if (
                        !empty($targetName)
                        && isset(self::$_buildScript['targets'][$targetName])
                        && is_array(self::$_buildScript['targets'][$targetName])
                    ) {
                        self::run($controller, $targetName);
                    }
                    else {
                        $controller->stderr('Invalid target: '.$targetName, Console::FG_RED);
                    }
                    break;

                case 'out':
                    $function = [$controller, 'stdout'];
                    $functionParams[0] = (!empty($functionParams[0])
                        ? self::parseStringParams($functionParams[0])."\n"
                        : ''
                    ); // Text to print
                    break;

                case 'err':
                    $function = [$controller, 'stderr'];
                    $functionParams[0] = (!empty($functionParams[0])
                        ? self::parseStringParams($functionParams[0])."\n"
                        : ''
                    ); // Text to print
                    $functionParams[1] = (!empty($functionParams[1]) ? $functionParams[1] : Console::FG_RED);
                    break;

                case 'prompt':
                case 'select':
                case 'confirm':
                    $varName = array_shift($functionParams);

                    $text = (!empty($functionParams[0]) ? $functionParams[0] : ''); // Prompt string

                    if (!empty($varName) && $controller->interactive) {

                        if ($cmdName === 'prompt' || $cmdName === 'select') {
                            // Note: options parameter works differently between prompt and select methods
                            // http://www.yiiframework.com/doc-2.0/yii-console-controller.html#select()-detail
                            $options = (!empty($functionParams[1]) ? $functionParams[1] : []); // options
                        }

                        if ($cmdName === 'prompt') {
                            if (empty($options['default'])) {
                                $options['default'] = $controller->getParamVal($varName, $params);
                            }

                            $params[$varName] = $controller->prompt($text, $options);
                        }
                        elseif ($cmdName === 'select') {
                            /** @noinspection PhpUndefinedVariableInspection */
                            $params[$varName] = $controller->select($text, $options);
                        }
                        elseif ($cmdName === 'confirm') {
                            $confirmDefault = (!empty($functionParams[1]) ? $functionParams[1] : false); // default
                            $params[$varName] = $controller->confirm($text, $confirmDefault);
                        }
                    }

                    break;

                case 'if':
                    $result = (!empty($functionParams[0]) ? eval('return '.$functionParams[0].';') : false);

                    $controller->stdout('IF result: '.var_export($result, true)."\n", Console::FG_PURPLE);

                    if ($result && !empty($functionParams[1]) && is_array($functionParams[1])) {
                        self::_process($controller, $functionParams[1]);
                    }

                    break;

                case 'recipe':
                    $recipeName = (!empty($functionParams[0]) ? $functionParams[0] : '');
                    $recipeTarget = (!empty($functionParams[1]) ? $functionParams[1] : '');
                    self::_runRecipe($controller, $recipeName, $recipeTarget);
                    break;

                default:
                    self::_runCommand($controller, $cmdName, $functionParams);
                    break;
            }

            if (!empty($function)) {
              call_user_func_array($function, $functionParams);
            }
        }

    }

    public static function parseStringParams($string) {

        $string = preg_replace_callback('/{{(.*)}}/', function(array $matches) {

                $var = (isset(self::$_params[$matches[1]]) ? self::$_params[$matches[1]] : null);

                if (is_array($var)) {
                    $res = implode(', ', $var);
                }
                elseif (is_string($var)) {
                    $res = $var;
                }
                else {
                    $res = "[".gettype($var)."]";
                }

                return $res;
            },
            $string
        );

        return $string;
    }

    public static function parsePath($path) {
        return self::parseStringParams(Yii::getAlias($path));
    }

    private static function _getCommand($cmdName, $userCommand = false) {
        $command = false;

        $commandClass = ucfirst($cmdName).'Command';

        if (!$userCommand) {
            $commandsDir = __DIR__.'/commands';
            $baseNamespace = 'app\\lib\\commands\\';
        }
        else {
            $commandsDir = Yii::getAlias('@buildScripts/commands');
            $baseNamespace = 'buildScripts\\commands\\';
        }

        $commandFile = $commandsDir.'/'.$commandClass.'.php';

        if (file_exists($commandFile) && method_exists($baseNamespace.$commandClass, 'run')) {
            $command = $baseNamespace.$commandClass;
        }
        elseif (!$userCommand) {
            $command = self::_getCommand($cmdName, true);
        }

        return $command;
    }

    private static function _getReqs($cmdName) {
        $reqs = false;
        $commandsDir = __DIR__.'/commands';

        $commandClass = ucfirst($cmdName).'Reqs';
        $commandFile = $commandsDir.'/'.$commandClass.'.php';

        if (file_exists($commandFile)) {
            $reqs = 'app\\lib\\commands\\'.$commandClass;
        }

        return $reqs;
    }

    private static function _runCommand(BaseConsoleController $controller, $cmdName, $functionParams) {

        $command = self::_getCommand($cmdName);
        if ($command) {
            /** @noinspection PhpUndefinedMethodInspection */
            $command::run($controller, $functionParams, self::$_params);
        }

    }

    private static function _setAliases(BaseConsoleController $controller) {
        Yii::setAlias('@workspace', $controller->workspace);
        Yii::setAlias('@buildScripts', $controller->workspace.'/'.$controller->getScriptFolder());
    }

    private static function _loadRequirements(BaseConsoleController $controller, $reqs) {


        foreach ($reqs as $reqId) {

            $reqInfo = [];
            preg_match('/(.*)(?:--(\w+)$)/', $reqId, $reqInfo);

            if (empty($reqInfo[1]) || empty($reqInfo[2])) {
                throw new Exception("Invalid requirement: ".$reqId);
            }

            $requirement = $reqInfo[1];
            $type = $reqInfo[2];


            if (!isset(self::$_loadedRequirements[$reqId])) {

                switch ($type) {
                    case 'command':
                        $commandReqs = self::_getReqs($requirement);

                        self::$_loadedRequirements[$reqId] = [
                            'type' => $type,
                            'reqsObject' => $commandReqs
                        ];

                        if ($commandReqs) {
                            $controller->attachBehavior($reqId, $commandReqs);
                            /** @noinspection PhpUndefinedMethodInspection */
                            $controller->extraOptions = array_merge(
                                $controller->extraOptions,
                                $commandReqs::getCommandOptions()
                            );
                        }
                        break;

                    case 'recipe':
                        self::$_loadedRequirements[$reqId] = ['type' => $type];
                        self::_loadRecipe($controller, $requirement);
                        break;

                    default:
                        throw new Exception("Invalid requirement: ".$type);
                        break;
                }
            }
        }
    }

    /**
     * For every required command, make sure that the command can be used inside of the
     * current build file.
     * Note: target specific parameters / requirements should be checked from the run method
     * of the command.
     *
     * @param BaseConsoleController $controller
     */
    private static function _checkAllRequirements(BaseConsoleController $controller) {
        foreach (self::$_loadedRequirements as $reqId=>$reqInfo) {

            switch ($reqInfo['type']) {
                case 'command':
                    if (
                        !empty($reqInfo['reqsObject'])
                        && method_exists($reqInfo['reqsObject'], 'checkRequirements')
                    ) {
                        $reqInfo['reqsObject']::checkRequirements($controller, self::$_params);
                    }
                    break;
            }
        }
    }

    private static function _setParamsFromOptions(BaseConsoleController $controller) {
        $params = & self::$_params;

        $allOptions = array_merge($controller->options(''), $controller->extraOptions);
        $providedOptions = $controller->getProvidedOptions();

        foreach ($allOptions as $optName) {
            if (isset($providedOptions[$optName]) && isset($params[$optName])) {
                $params[$optName] = $controller->$optName;
                $controller->$optName = null;
            }
        }
    }

    /**
     * Compare the php files of the built-in commands and recipes with the user defined ones.
     * If the user defined ones have the same name of the built-in ones, throws and exception.
     *
     * File names are converted to lower case so that the check is case insensitive.
     *
     * @throws \yii\console\Exception
     */
    private static function _checkUserScripts() {

        $buildScripts = Yii::getAlias('@buildScripts');

        $builtInCommands = self::getLowercaseBaseNames(glob(__DIR__.'/commands/*.php'));
        $builtInRecipes = self::getLowercaseBaseNames(glob(__DIR__.'/recipes/*.php'));

        $userCommands = self::getLowercaseBaseNames(glob($buildScripts.'/commands/*.php'));
        $userRecipes = self::getLowercaseBaseNames(glob($buildScripts.'/recipes/*.php'));

        $overridingCommands = array_intersect($builtInCommands, $userCommands);
        $overridingRecipes = array_intersect($builtInRecipes, $userRecipes);


        if (!empty($overridingCommands) || !empty($overridingRecipes)) {
            throw new Exception(
                'You cannot override built-in commands or recipes: '
                .trim(implode(", ", $overridingCommands).", ".implode(".", $overridingRecipes), ', ')
            );
        }
    }

    /**
     * Returns an array containing the base name of each element
     * of fileList in lower case
     *
     * @param array $fileList
     *
     * @return array
     */
    public static function getLowercaseBaseNames($fileList) {
        $res = [];

        foreach ($fileList as $path) {
            $res [] = strtolower(basename($path));
        }

        return $res;
    }

} 