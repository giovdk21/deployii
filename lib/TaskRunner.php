<?php
/**
 * DeploYii - TaskRunner
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;

use yii\console\Exception;
use yii\helpers\Console;
use Yii;

class TaskRunner
{

    /** @var array the content of the build.php file */
    private static $_buildScript = [];
    /** @var array build script parameters */
    private static $_params = [];
    /** @var array list of loaded requirements */
    private static $_loadedRequirements = [];
    /** @var array the content of the loaded recipes */
    private static $_recipes = [];

    /** @var BaseConsoleController The controller instance */
    public static $controller = '';

    /**
     * Initialise the TaskRunner:
     * - set aliases
     * - check script version / compatibility
     * - check that the user defined commands and recipes do not override the built-in ones
     * - load requirements and initialises the extra parameters
     * - load default parameters from build file
     * - overwrite script parameters with values passed from the command line
     * - check commands global requirements to make sure that all the commands can be ran
     *
     * @param BaseConsoleController $controller The controller which initialises the TaskRunner
     * @param string                $buildFile
     *
     * @throws \yii\console\Exception
     */
    public static function init(BaseConsoleController $controller, $buildFile)
    {
        self::$_params = [];
        self::$_loadedRequirements = [];

        self::$controller = $controller;

        /** @noinspection PhpIncludeInspection */
        self::$_buildScript = require($buildFile);

        // Set default aliases
        self::_setAliases();

        // Check script compatibility:
        if (!empty(self::$_buildScript['deployiiVersion'])) {
            VersionManager::checkBuildVersion(self::$_buildScript['deployiiVersion']);
        } else {
            throw new Exception('Please specify the "deployiiVersion" in your build script');
        }

        // Check that the user defined commands and recipes do not override the built-in ones:
        self::_checkUserScripts();

        // Load requirements and initialise the extra parameters:
        if (!empty(self::$_buildScript['require'])) {
            self::_loadRequirements(self::$_buildScript['require']);
        }


        // Load default parameters from build file:
        if (!empty(self::$_buildScript['params'])) {
            self::$_params = array_merge(self::$_params, self::$_buildScript['params']);
        }

        // overwrite script parameters with values passed from the command line:
        self::_setParamsFromOptions();

        // check commands global requirements to make sure that all the commands can be ran:
        self::_checkAllRequirements();
    }

    /**
     * Run the specified build target
     *
     * @param string $target the target to be ran; if not specified "default" will be used
     *
     * @return int the exit code
     * @throws \yii\console\Exception if init() has not been called yet
     */
    public static function run($target = '')
    {
        $exitCode = 0;
        $target = (empty($target) ? 'default' : $target);

        if (empty(self::$_buildScript) || empty(self::$controller)) {
            throw new Exception('Empty script: init() not called or invalid build file');
        }

        if (isset(self::$_buildScript['targets'][$target])) { // run the selected target
            TaskRunner::_runTarget(self::$_buildScript['targets'][$target], $target);
        } else {
            self::$controller->stderr('Target not found: '.$target, Console::FG_RED);
            $exitCode = 1;
        }

        return $exitCode;
    }

    /**
     * Run the specified target of the given script (build, recipe, ...)
     *
     * @param array  $targetScript the content of the script
     * @param string $targetName   the target to be ran; if not specified "default" will be used
     */
    private static function _runTarget($targetScript, $targetName = '')
    {
        self::$controller->stdout("Running ".$targetName."...\n", Console::FG_GREEN, Console::BOLD);
        self::_process($targetScript);
    }

    /**
     * Load the required recipe and its requirements.
     * Note: recipes have to be loaded on init() declaring them as requirements
     *
     * This method will first look for built-in recipes and then for the user defined ones.
     * TODO: log warning message if the requested recipe is not found
     *
     * @param string $recipeName
     * @param bool   $userRecipe whether to search inside the user build scripts folder
     *
     * @return bool|mixed
     */
    private static function _loadRecipe($recipeName, $userRecipe = false)
    {
        $recipeScript = false;

        $recipeClass = ucfirst($recipeName).'Recipe';

        if (!$userRecipe) {
            $recipesDir = __DIR__.'/recipes';
        } else {
            $recipesDir = Yii::getAlias('@buildScripts/recipes');
        }

        $recipeFile = $recipesDir.'/'.$recipeClass.'.php';

        if (file_exists($recipeFile)) {
            /** @noinspection PhpIncludeInspection */
            $recipeScript = require($recipeFile);
        } elseif (!$userRecipe) {
            $recipeScript = self::_loadRecipe($recipeName, true);
        }

        // Load requirements and initialise the extra parameters:
        if (!empty($recipeScript['require'])) {
            self::_loadRequirements($recipeScript['require']);
        }

        self::$_recipes[$recipeName] = $recipeScript;
        return $recipeScript;
    }

    /**
     * Run the requested recipe script target
     *
     * @param string $recipeName
     * @param string $recipeTarget the target to be ran; if not specified "default" will be used
     *
     * @throws \yii\console\Exception
     */
    private static function _runRecipe($recipeName, $recipeTarget = '')
    {
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

        self::$controller->stdout(
            "Running recipe: {$recipeName} [{$recipeTarget}]...\n",
            Console::FG_GREEN,
            Console::BOLD
        );
        self::_process($recipeScript);
    }

    /**
     * Process the script
     *
     * @param array $script the (build, recipe, ...) script
     */
    private static function _process($script)
    {

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
                        self::run($targetName);
                    } else {
                        self::$controller->stderr('Invalid target: '.$targetName, Console::FG_RED);
                    }
                    break;

                case 'out':
                    $function = [self::$controller, 'stdout'];
                    $functionParams[0] = (!empty($functionParams[0])
                        ? self::parseStringParams($functionParams[0])."\n"
                        : ''
                    ); // Text to print
                    break;

                case 'err':
                    $function = [self::$controller, 'stderr'];
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

                    if (!empty($varName) && self::$controller->interactive) {

                        if ($cmdName === 'prompt' || $cmdName === 'select') {
                            // Note: options parameter works differently between prompt and select methods
                            // http://www.yiiframework.com/doc-2.0/yii-console-controller.html#select()-detail
                            $options = (!empty($functionParams[1]) ? $functionParams[1] : []); // options
                        }

                        if ($cmdName === 'prompt') {
                            if (empty($options['default']) && !empty($params[$varName])) {
                                $options['default'] = $params[$varName];
                            }

                            $params[$varName] = self::$controller->prompt($text, $options);
                        } elseif ($cmdName === 'select') {
                            /** @noinspection PhpUndefinedVariableInspection */
                            $params[$varName] = self::$controller->select($text, $options);
                        } elseif ($cmdName === 'confirm') {
                            $confirmDefault = (!empty($functionParams[1]) ? $functionParams[1] : false); // default
                            $params[$varName] = self::$controller->confirm($text, $confirmDefault);
                        }
                    }

                    break;

                case 'if':
                    $result = (!empty($functionParams[0]) ? eval('return '.$functionParams[0].';') : false);

                    // TODO: log instead of print out:
                    //self::$controller->stdout('IF result: '.var_export($result, true)."\n", Console::FG_PURPLE);

                    if ($result && !empty($functionParams[1]) && is_array($functionParams[1])) {
                        self::_process($functionParams[1]); // "if" code block
                    } elseif (!$result && !empty($functionParams[2]) && is_array($functionParams[2])) {
                        self::_process($functionParams[2]); // "else" code block
                    }

                    break;

                case 'recipe':
                    $recipeName = (!empty($functionParams[0]) ? $functionParams[0] : '');
                    $recipeTarget = (!empty($functionParams[1]) ? $functionParams[1] : '');
                    self::_runRecipe($recipeName, $recipeTarget);
                    break;

                default:
                    self::_runCommand($cmdName, $functionParams);
                    break;
            }

            if (!empty($function)) {
                call_user_func_array($function, $functionParams);
            }
        }

    }

    /**
     * Replace build placeholders with the corresponding build parameters.
     * Placeholders are defined as {{varName}}
     *
     * @param string $string the text to be parsed
     *
     * @return string
     */
    public static function parseStringParams($string)
    {

        $string = preg_replace_callback(
            '/{{(.*)}}/',
            function (array $matches) {

                $var = (isset(self::$_params[$matches[1]]) ? self::$_params[$matches[1]] : null);

                if (is_array($var)) {
                    $res = implode(', ', $var);
                } elseif (is_string($var)) {
                    $res = $var;
                } elseif (is_int($var) || is_double($var)) {
                    $res = (string)$var;
                } elseif (is_bool($var)) {
                    $res = ($var ? 'true' : 'false');
                } else {
                    $res = "[".gettype($var)."]";
                }

                return $res;
            },
            $string
        );

        return $string;
    }

    /**
     * Extract path aliases from a string and replace them with the
     * value from Yii::getAlias()
     *
     * @param string $string      the text to be parsed
     * @param bool   $parseParams whether to also parse the string placeholders
     *
     * @return string
     */
    public static function parseStringAliases($string, $parseParams = true)
    {

        $string = preg_replace_callback(
            '/(?:(?:^|[\\s=])(@[^\\s\\/\\\]+))/',
            function (array $matches) {
                $res = str_replace($matches[1], Yii::getAlias($matches[1]), $matches[0]);
                return $res;
            },
            $string
        );

        if ($parseParams) {
            $string = self::parseStringParams($string);
        }

        return $string;
    }

    /**
     * Translates the path alias into an actual path and parses
     * the placeholders, if any.
     *
     * @param string $path
     *
     * @return string
     */
    public static function parsePath($path)
    {
        return self::parseStringParams(Yii::getAlias($path));
    }

    /**
     * Get the requested command
     *
     * This method will first look for built-in commands and then for the user defined ones.
     * TODO: log warning message if the requested command is not found
     *
     * @param string $cmdName     The name of the command
     * @param bool   $userCommand whether to search inside the user build scripts folder
     *
     * @return bool|string
     */
    private static function _getCommand($cmdName, $userCommand = false)
    {
        $command = false;

        $commandClass = ucfirst($cmdName).'Command';

        if (!$userCommand) {
            $commandsDir = __DIR__.'/commands';
            $baseNamespace = 'app\\lib\\commands\\';
        } else {
            $commandsDir = Yii::getAlias('@buildScripts/commands');
            $baseNamespace = 'buildScripts\\commands\\';
        }

        $commandFile = $commandsDir.'/'.$commandClass.'.php';

        if (file_exists($commandFile) && method_exists($baseNamespace.$commandClass, 'run')) {
            $command = $baseNamespace.$commandClass;
        } elseif (!$userCommand) {
            $command = self::_getCommand($cmdName, true);
        }

        return $command;
    }

    /**
     * Load the requirements file for the given command, if present.
     *
     * @param string $cmdName The name of the command
     *
     * @return bool|string
     */
    private static function _getReqs($cmdName)
    {
        $reqs = false;
        $commandsDir = __DIR__.'/commands';

        $commandClass = ucfirst($cmdName).'Reqs';
        $commandFile = $commandsDir.'/'.$commandClass.'.php';

        if (file_exists($commandFile)) {
            $reqs = 'app\\lib\\commands\\'.$commandClass;
        }

        return $reqs;
    }

    /**
     * Run the requested commands
     * @see self::_getCommand
     *
     * @param string $cmdName        The name of the command
     * @param array  $functionParams the parameters passed to the command
     */
    private static function _runCommand($cmdName, $functionParams)
    {

        $command = self::_getCommand($cmdName);
        if ($command) {
            /** @noinspection PhpUndefinedMethodInspection */
            $command::run($functionParams, self::$_params);
        }

    }

    /**
     * Set global aliases
     */
    private static function _setAliases()
    {
        Yii::setAlias('@workspace', self::$controller->workspace);
        Yii::setAlias('@buildScripts', self::$controller->workspace.'/'.self::$controller->getScriptFolder());
    }

    /**
     * Load commands / recipes requirements
     *
     * @param array $reqs List of requirements in the format of ['<name>--<type>']
     *
     * @throws \yii\console\Exception
     */
    private static function _loadRequirements($reqs)
    {


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
                            'type'       => $type,
                            'reqsObject' => $commandReqs
                        ];

                        if ($commandReqs) {
                            self::$controller->attachBehavior($reqId, $commandReqs);

                            /** @noinspection PhpUndefinedMethodInspection */
                            $commandOptions = $commandReqs::getCommandOptions();

                            foreach ($commandOptions as $cmdOptionName => $cmdOptionDefault) {
                                // set the value of the extra parameter, if not already set
                                // (if the value is not passed as a command line option)
                                if (!isset(self::$controller->extraParams[$cmdOptionName])) {
                                    self::$controller->extraParams[$cmdOptionName] = $cmdOptionDefault;
                                }
                            }
                        }
                        break;

                    case 'recipe':
                        self::$_loadedRequirements[$reqId] = ['type' => $type];
                        self::_loadRecipe($requirement);
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
     */
    private static function _checkAllRequirements()
    {
        foreach (self::$_loadedRequirements as $reqId => $reqInfo) {

            switch ($reqInfo['type']) {
                case 'command':
                    if (
                        !empty($reqInfo['reqsObject'])
                        && method_exists($reqInfo['reqsObject'], 'checkRequirements')
                    ) {
                        $reqInfo['reqsObject']::checkRequirements(self::$_params);
                    }
                    break;
            }
        }
    }

    /**
     * If an option passed via command line has also been defined as a script parameter,
     * the script parameter will be overwritten with the value passed from the command line.
     *
     * 1 - extraParams are stored if the option is passed via command line
     * (in BaseConsoleController->runAction()) (empty strings are ignored).
     * We know which information are coming from the command line
     * (and not from the command defaults) thanks to self::$controller->getProvidedOptions();
     * 2 - extraParams is then also populated by TaskRunner::_loadRequirements() with
     * all the available (default) options defined by the <Command>Reqs class
     * which are not been set from the command line (see point 1);
     * 3 - if the extra parameter is not defined as a script parameter, or if we are overriding
     * it from the command line, we initialise/overwrite the script parameter
     * with the extra parameter.
     */
    private static function _setParamsFromOptions()
    {

        $params = & self::$_params;
        $providedOptions = self::$controller->getProvidedOptions();

        foreach (self::$controller->extraParams as $optName => $optVal) {
            if (
                !isset($params[$optName])
                || (isset($providedOptions[$optName]) && isset($params[$optName]))
            ) {
                $params[$optName] = $optVal;
            }
        }
    }

    /**
     * Compare the php files of the built-in commands and recipes with the user defined ones.
     * If the user defined ones have the same name of the built-in ones, throws and exception.
     *
     * File names are converted to lower case so that the check is case insensitive.
     *
     * @throws \yii\console\Exception if a built-in command or recipe has been overridden
     */
    private static function _checkUserScripts()
    {

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
    public static function getLowercaseBaseNames($fileList)
    {
        $res = [];

        foreach ($fileList as $path) {
            $res [] = strtolower(basename($path));
        }

        return $res;
    }

}