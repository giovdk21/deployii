<?php
/**
 * DeploYii - TaskRunner
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;

use yii\helpers\Console;
use Yii;

class TaskRunner
{

    /** @var array the content of the build.php file */
    private $_buildScript = [];
    /** @var array build script parameters */
    private $_params = [];
    /** @var array parameters loaded from the config.php file (obfuscated in the log) */
    private $_configParams = [];
    /** @var array list of loaded requirements */
    private $_loadedRequirements = [];
    /** @var array the content of the loaded recipes */
    private $_recipes = [];

    /** @var BaseConsoleController The controller instance */
    public $controller = '';

    /**
     * Initialise the TaskRunner:
     * - set aliases
     * - check build script & home directory version / compatibility
     * - check that the user defined commands and recipes do not override the built-in ones
     * - load requirements and initialises the extra parameters
     * - load default parameters from build file
     * - load build script configuration (if present) and ask/set current environment (if needed)
     * - overwrite script parameters with values passed from the command line
     * - check commands global requirements to make sure that all the commands can be ran
     * - log build script parameters
     *
     * @param BaseConsoleController $controller The controller which initialises the TaskRunner
     * @param string                $buildFile
     *
     * @throws \yii\console\Exception
     */
    public function __construct(BaseConsoleController $controller, $buildFile)
    {
        $this->_params = [];
        $this->_loadedRequirements = [];

        $this->controller = $controller;

        if (file_exists($buildFile)) {
            /** @noinspection PhpIncludeInspection */
            $this->_buildScript = require($buildFile);
        } else {
            Log::throwException('Build script not found: '.$buildFile);
        }

        // Set default aliases
        $this->_setAliases();

        // Check script compatibility:
        if (!empty($this->_buildScript['deployiiVersion'])) {
            VersionManager::checkBuildVersion($this->_buildScript['deployiiVersion']);
        } else {
            Log::throwException('Please specify the "deployiiVersion" in your build script');
        }

        // Check that the user defined commands and recipes do not override the built-in ones:
        $this->_checkUserScripts();

        // Load requirements and initialise the extra parameters:
        if (!empty($this->_buildScript['require'])) {
            $this->_loadRequirements($this->_buildScript['require']);
        }


        // Load default parameters from build file:
        if (!empty($this->_buildScript['params'])) {
            $this->_params = array_merge($this->_params, $this->_buildScript['params']);
        }

        // load build script configuration (if present) and ask/set current environment (if needed)
        $this->_loadScriptConfig();

        // overwrite script parameters with values passed from the command line:
        $this->_setParamsFromOptions();

        // check commands global requirements to make sure that all the commands can be ran:
        $this->_checkAllRequirements();

        // log build script parameters
        $this->logParams();
    }

    /**
     * Run the specified build target
     *
     * @param string $target the target to be ran; if not specified "default" will be used
     *
     * @return int the exit code
     * @throws \yii\console\Exception if init() has not been called yet
     */
    public function run($target = '')
    {
        $exitCode = 0;
        $target = (empty($target) ? 'default' : $target);

        if (empty($this->_buildScript) || empty($this->controller)) {
            Log::throwException('Empty script: init() not called or invalid build file');
        }

        if (isset($this->_buildScript['targets'][$target])) { // run the selected target
            $this->_runTarget($this->_buildScript['targets'][$target], $target);
        } else {
            $this->controller->stderr('Target not found: '.$target, Console::FG_RED);
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
    private function _runTarget($targetScript, $targetName = '')
    {
        $this->controller->stdout("Running ".$targetName."...\n", Console::FG_GREEN, Console::BOLD);
        $this->_process($targetScript);
    }

    /**
     * Load the required recipe and its requirements.
     * Note: recipes have to be loaded on init() declaring them as requirements
     *
     * This method will first look for built-in recipes and then for the user defined ones.
     * TODO: log warning message if the requested recipe is not found
     *
     * @param string $recipeName
     * @param bool   $globalRecipe whether to search inside the DeploYii home folder
     * @param bool   $userRecipe   whether to search inside the user build scripts folder
     *
     * @return bool|mixed
     */
    private function _loadRecipe($recipeName, $globalRecipe = false, $userRecipe = false)
    {
        $recipeScript = false;

        $recipeClass = ucfirst($recipeName).'Recipe';

        if ($globalRecipe && !$userRecipe) {
            $recipesDir = Yii::getAlias('@home/recipes');
        } elseif ($userRecipe) {
            $recipesDir = Yii::getAlias('@buildScripts/recipes');
        } else {
            $recipesDir = __DIR__.'/recipes';
        }

        $recipeFile = $recipesDir.'/'.$recipeClass.'.php';

        if (file_exists($recipeFile)) {
            Log::logger()->addInfo('Loading recipe file: {file}', ['file' => $recipeFile]);
            /** @noinspection PhpIncludeInspection */
            $recipeScript = require($recipeFile);
        } elseif (!$globalRecipe && !$userRecipe) {
            $recipeScript = $this->_loadRecipe($recipeName, true);
        } elseif (!$userRecipe) {
            $recipeScript = $this->_loadRecipe($recipeName, false, true);
        }

        // Load requirements and initialise the extra parameters:
        if (!empty($recipeScript['require'])) {
            $this->_loadRequirements($recipeScript['require']);
        }

        $this->_recipes[$recipeName] = $recipeScript;
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
    private function _runRecipe($recipeName, $recipeTarget = '')
    {
        $recipeTarget = (empty($recipeTarget) ? 'default' : $recipeTarget);

        if (
            empty($recipeName)
            || empty($this->_recipes[$recipeName])
            || !is_array($this->_recipes[$recipeName])
            || !isset($this->_recipes[$recipeName]['targets'])
            || empty($this->_recipes[$recipeName]['targets'][$recipeTarget])
        ) {
            Log::throwException("Invalid recipe / target: {$recipeName} [{$recipeTarget}]");
        }

        $recipeScript = $this->_recipes[$recipeName]['targets'][$recipeTarget];

        $this->controller->stdout(
            "Running recipe: {$recipeName} [{$recipeTarget}]...\n",
            Console::FG_GREEN,
            Console::BOLD
        );
        $this->_process($recipeScript);
    }

    /**
     * Process the script
     *
     * @param array $script the (build, recipe, ...) script
     */
    private function _process($script)
    {

        $params = & $this->_params;

        foreach ($script as $functionParams) {

            $cmdName = array_shift($functionParams);
            $function = null;

            switch ($cmdName) {

                case 'target':
                    $targetName = (!empty($functionParams[0]) ? $functionParams[0] : '');
                    if (
                        !empty($targetName)
                        && isset($this->_buildScript['targets'][$targetName])
                        && is_array($this->_buildScript['targets'][$targetName])
                    ) {
                        $this->run($targetName);
                    } else {
                        $this->controller->stderr('Invalid target: '.$targetName, Console::FG_RED);
                    }
                    break;

                case 'out':
                    $function = [$this->controller, 'stdout'];
                    $functionParams[0] = (!empty($functionParams[0])
                        ? $this->parseStringParams($functionParams[0])."\n"
                        : ''
                    ); // Text to print
                    break;

                case 'err':
                    $function = [$this->controller, 'stderr'];
                    $functionParams[0] = (!empty($functionParams[0])
                        ? $this->parseStringParams($functionParams[0])."\n"
                        : ''
                    ); // Text to print
                    $functionParams[1] = (!empty($functionParams[1]) ? $functionParams[1] : Console::FG_RED);
                    break;

                case 'warn':
                    $function = [$this->controller, 'warn'];
                    $functionParams[0] = (!empty($functionParams[0])
                        ? $this->parseStringParams($functionParams[0])."\n"
                        : ''
                    ); // Text to print
                    break;

                case 'prompt':
                case 'select':
                case 'confirm':
                    $varName = array_shift($functionParams);

                    $text = (!empty($functionParams[0]) ? $functionParams[0] : ''); // Prompt string

                    if (!empty($varName) && $this->controller->interactive) {

                        if ($cmdName === 'prompt' || $cmdName === 'select') {
                            // Note: options parameter works differently between prompt and select methods
                            // http://www.yiiframework.com/doc-2.0/yii-console-controller.html#select()-detail
                            $options = (!empty($functionParams[1]) ? $functionParams[1] : []); // options
                        }

                        if ($cmdName === 'prompt') {
                            if (empty($options['default']) && !empty($params[$varName])) {
                                $options['default'] = $params[$varName];
                            }

                            $params[$varName] = $this->controller->prompt($text, $options);
                        } elseif ($cmdName === 'select') {
                            /** @noinspection PhpUndefinedVariableInspection */
                            $params[$varName] = $this->controller->select($text, $options);
                        } elseif ($cmdName === 'confirm') {
                            $confirmDefault = (!empty($functionParams[1]) ? $functionParams[1] : false); // default
                            $params[$varName] = $this->controller->confirm($text, $confirmDefault);
                        }
                    }

                    break;

                case 'if':
                    $result = (!empty($functionParams[0]) ? eval('return '.$functionParams[0].';') : false);

                    Log::logger()->addDebug(
                        'Evaluating IF condition {condition}: {result}',
                        [
                            'condition' => $functionParams[0],
                            'result'    => var_export($result, true),
                        ]
                    );

                    if ($result && !empty($functionParams[1]) && is_array($functionParams[1])) {
                        $this->_process($functionParams[1]); // "if" code block
                    } elseif (!$result && !empty($functionParams[2]) && is_array($functionParams[2])) {
                        $this->_process($functionParams[2]); // "else" code block
                    }

                    break;

                case 'recipe':
                    $recipeName = (!empty($functionParams[0]) ? $functionParams[0] : '');
                    $recipeTarget = (!empty($functionParams[1]) ? $functionParams[1] : '');
                    $this->_runRecipe($recipeName, $recipeTarget);
                    break;

                default:
                    $this->_runCommand($cmdName, $functionParams);
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
    public function parseStringParams($string)
    {

        $string = preg_replace_callback(
            '/{{(.*)}}/',
            function (array $matches) {
                return $this->getParamString($matches[1]);
            },
            $string
        );

        return $string;
    }

    public function getParamString($paramName)
    {
        $var = (isset($this->_params[$paramName]) ? $this->_params[$paramName] : null);

        if (!in_array($paramName, $this->_configParams)) {
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
        } else {
            $res = str_repeat('*', strlen((string)$var));
        }

        return $res;
    }

    /**
     * Log build script parameters
     */
    public function logParams()
    {
        $paramList = '';

        foreach ($this->_params as $paramName => $value) {
            $paramList .= $paramName.': '.$this->getParamString($paramName)."\n";
        }

        Log::logger()->addInfo("Build script parameters: \n\n".$paramList);
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
    public function parseStringAliases($string, $parseParams = true)
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
            $string = $this->parseStringParams($string);
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
    public function parsePath($path)
    {
        return $this->parseStringParams(Yii::getAlias($path));
    }

    /**
     * Get the requested command
     *
     * This method will first look for built-in commands and then for the user defined ones.
     * TODO: log warning message if the requested command is not found
     *
     * @param string $cmdName       The name of the command
     * @param bool   $globalCommand whether to search inside the DeploYii home folder
     * @param bool   $userCommand   whether to search inside the user build scripts folder
     *
     * @return bool|string
     */
    private function _getCommand($cmdName, $globalCommand = false, $userCommand = false)
    {
        $command = false;

        $commandClass = ucfirst($cmdName).'Command';

        if ($globalCommand && !$userCommand) {
            $commandsDir = Yii::getAlias('@home/commands');
            $baseNamespace = 'home\\commands\\';
        } elseif ($userCommand) {
            $commandsDir = Yii::getAlias('@buildScripts/commands');
            $baseNamespace = 'buildScripts\\commands\\';
        } else {
            $commandsDir = __DIR__.'/commands';
            $baseNamespace = 'app\\lib\\commands\\';
        }

        $commandFile = $commandsDir.'/'.$commandClass.'.php';

        if (file_exists($commandFile) && method_exists($baseNamespace.$commandClass, 'run')) {
            $fullClassName = $baseNamespace.$commandClass;
            $command = new $fullClassName($this);
            Log::logger()->addInfo(
                'Getting command {command} from {file}',
                [
                    'command' => $fullClassName,
                    'file'    => $commandFile,
                ]
            );
        } elseif (!$globalCommand && !$userCommand) {
            $command = $this->_getCommand($cmdName, true);
        } elseif (!$userCommand) {
            $command = $this->_getCommand($cmdName, false, true);
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
    private function _getReqs($cmdName)
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
     * @see $this->_getCommand
     *
     * @param string $cmdName        The name of the command
     * @param array  $functionParams the parameters passed to the command
     */
    private function _runCommand($cmdName, $functionParams)
    {

        $command = $this->_getCommand($cmdName);
        if ($command) {
            /** @noinspection PhpUndefinedMethodInspection */
            $command->run($functionParams, $this->_params);
        }

    }

    /**
     * Set global aliases
     */
    private function _setAliases()
    {
        Yii::setAlias('@workspace', $this->controller->workspace);
        Yii::setAlias('@buildScripts', $this->controller->workspace.'/'.$this->controller->getScriptFolder());
        Yii::setAlias('@home', Shell::getHomeDir()); // DeploYii home directory
    }

    /**
     * Load commands / recipes requirements
     *
     * @param array $reqs List of requirements in the format of ['<name>--<type>']
     *
     * @throws \yii\console\Exception
     */
    private function _loadRequirements($reqs)
    {


        foreach ($reqs as $reqId) {

            $reqInfo = [];
            preg_match('/(.*)(?:--(\w+)$)/', $reqId, $reqInfo);

            if (empty($reqInfo[1]) || empty($reqInfo[2])) {
                Log::throwException("Invalid requirement: ".$reqId);
            }

            $requirement = $reqInfo[1];
            $type = $reqInfo[2];


            if (!isset($this->_loadedRequirements[$reqId])) {

                switch ($type) {
                    case 'command':
                        $commandReqs = $this->_getReqs($requirement);

                        $this->_loadedRequirements[$reqId] = [
                            'type'       => $type,
                            'reqsObject' => $commandReqs
                        ];

                        if ($commandReqs) {
                            $this->controller->attachBehavior($reqId, $commandReqs);

                            /** @noinspection PhpUndefinedMethodInspection */
                            $commandOptions = $commandReqs::getCommandOptions();

                            foreach ($commandOptions as $cmdOptionName => $cmdOptionDefault) {
                                // set the value of the extra parameter, if not already set
                                // (if the value is not passed as a command line option)
                                if (!isset($this->controller->extraParams[$cmdOptionName])) {
                                    $this->controller->extraParams[$cmdOptionName] = $cmdOptionDefault;
                                }
                            }
                        }
                        break;

                    case 'recipe':
                        $this->_loadedRequirements[$reqId] = ['type' => $type];
                        $this->_loadRecipe($requirement);
                        break;

                    default:
                        Log::throwException("Invalid requirement: ".$type);
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
    private function _checkAllRequirements()
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($this->_loadedRequirements as $reqId => $reqInfo) {

            switch ($reqInfo['type']) {
                case 'command':
                    if (
                        !empty($reqInfo['reqsObject'])
                        && method_exists($reqInfo['reqsObject'], 'checkRequirements')
                    ) {
                        $reqInfo['reqsObject']::checkRequirements($this->_params);
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
     * (and not from the command defaults) thanks to $this->controller->getProvidedOptions();
     * 2 - extraParams is then also populated by $this->_loadRequirements() with
     * all the available (default) options defined by the <Command>Reqs class
     * which are not been set from the command line (see point 1);
     * 3 - if the extra parameter is not defined as a script parameter, or if we are overriding
     * it from the command line, we initialise/overwrite the script parameter
     * with the extra parameter.
     */
    private function _setParamsFromOptions()
    {

        $params = & $this->_params;
        $providedOptions = $this->controller->getProvidedOptions();

        foreach ($this->controller->extraParams as $optName => $optVal) {
            if (
                !isset($params[$optName])
                || (isset($providedOptions[$optName]) && isset($params[$optName]))
            ) {
                $params[$optName] = $optVal;
            }
        }
    }

    /**
     * Load build script configuration (if present) and ask/set current environment (if needed)
     *
     * The current environment name is saved in params['environment']
     */
    private function _loadScriptConfig()
    {
        $env = '';
        $configFile = Yii::getAlias('@buildScripts/config.php');

        if (file_exists($configFile)) {

            Log::logger()->addDebug(
                'Loading build script configuration: {configFile}',
                ['configFile' => $configFile]
            );

            /** @noinspection PhpIncludeInspection */
            $config = require($configFile);

            $envConfig = [];
            $commonConfig = (!empty($config['config']['common']) ? $config['config']['common'] : []);
            if (!empty($config['environments']) && is_array($config['environments'])) {

                Log::logger()->addDebug(
                    'Available environments: {environments}',
                    ['environments' => implode(', ', array_keys($config['environments']))]
                );

                if (!property_exists($this->controller, 'env') || empty($this->controller->env)) {

                    if ($this->controller->interactive) {
                        $env = $this->controller->select(
                            'Please select your current environment',
                            $config['environments']
                        );
                    } elseif (isset($config['defaultEnvironment'])) {
                        $env = $config['defaultEnvironment'];
                    }
                } else {
                    $env = $this->controller->env;
                }

                if (!isset($config['environments'][$env])) {
                    Log::throwException('Invalid environment: '.$env);
                }

                Log::logger()->addDebug(
                    'Using environment: {env}',
                    ['env' => $env]
                );

                // Save the current environment name in params['environment']:
                $this->_params['environment'] = $env;

                $envConfig = (!empty($config['config'][$env]) ? $config['config'][$env] : []);
            }

            $flatCommonConfig = $this->flattenArray($commonConfig);
            $flatEnvConfig = $this->flattenArray($envConfig);

            $flatConfig = array_merge($flatCommonConfig, $flatEnvConfig);

            $this->_configParams = array_keys($flatConfig);
            $this->_params = array_merge($this->_params, $flatConfig);
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
    private function _checkUserScripts()
    {

        $buildScripts = Yii::getAlias('@buildScripts');
        $home = Shell::getHomeDir();

        $builtInCommands = $this->getLowercaseBaseNames(glob(__DIR__.'/commands/*.php'));
        $builtInRecipes = $this->getLowercaseBaseNames(glob(__DIR__.'/recipes/*.php'));

        $userGlobalCommands = $this->getLowercaseBaseNames(glob($home.'/commands/*.php'));
        $userGlobalRecipes = $this->getLowercaseBaseNames(glob($home.'/recipes/*.php'));

        $userBuildCommands = $this->getLowercaseBaseNames(glob($buildScripts.'/commands/*.php'));
        $userBuildRecipes = $this->getLowercaseBaseNames(glob($buildScripts.'/recipes/*.php'));

        $userCommands = array_merge($userBuildCommands, $userGlobalCommands);
        $userRecipes = array_merge($userBuildRecipes, $userGlobalRecipes);


        $overridingCommands = array_intersect($builtInCommands, $userCommands);
        $overridingRecipes = array_intersect($builtInRecipes, $userRecipes);

        if (!empty($overridingCommands) || !empty($overridingRecipes)) {
            Log::throwException(
                'You cannot override built-in commands or recipes: '
                .trim(implode(", ", $overridingCommands).", ".implode(".", $overridingRecipes), ', ')
            );
        }


        $overridingUserCommands = array_intersect($userGlobalCommands, $userBuildCommands);
        $overridingUserRecipes = array_intersect($userGlobalRecipes, $userBuildRecipes);

        if (!empty($overridingUserCommands) || !empty($overridingUserRecipes)) {
            $this->controller->warn(
                'You are overriding global commands or recipes: '
                .trim(implode(", ", $overridingUserCommands).", ".implode(".", $overridingUserRecipes), ', ')
                ."\n"
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
    public function getLowercaseBaseNames($fileList)
    {
        $res = [];

        foreach ($fileList as $path) {
            $res [] = strtolower(basename($path));
        }

        return $res;
    }

    /**
     * Flatten an array based on the given method;
     *
     * camelCase:
     * ['db' => ['username' => 'user]] becomes ['dbUsername' => 'user']
     *
     * dot:
     * ['db' => ['username' => 'user]] becomes ['db.username' => 'user']
     *
     * @param array  $array
     * @param string $method 'camelCase' or 'dot'
     * @param string $keyPrefix
     *
     * @return array
     */
    public function flattenArray($array, $method = 'camelCase', $keyPrefix = '')
    {
        $res = array();

        foreach ($array as $key => $val) {

            $newKey = $key;
            if (!empty($keyPrefix)) {
                switch ($method) {
                    case 'camelCase':
                        $newKey = $keyPrefix.ucfirst($key);
                        break;
                    case 'dot':
                        $newKey = $keyPrefix.'.'.$key;
                        break;
                }
            }

            if (is_array($val)) {
                $flattened = $this->flattenArray($val, $method, $newKey);
                $res = array_merge($res, $flattened);
            } else {
                $res[$newKey] = $val;
            }

        }

        return $res;
    }

}