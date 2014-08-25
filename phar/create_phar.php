<?php
/**
 * DeploYii - Create PHAR executable
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\phar;

require(__DIR__.'/../vendor/autoload.php');
require(__DIR__.'/../vendor/yiisoft/yii2/Yii.php');

use FilesystemIterator;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use \yii\helpers\FileHelper;
use yii\helpers\Json;


class CreatePhar
{

    private $_sourceDir;
    const DEBUG = false;

    public function __construct()
    {
        $this->_sourceDir = __DIR__.'/..';

        if (!file_exists(__DIR__.DIRECTORY_SEPARATOR.'composer.phar')) {
            // Download composer.phar
            exec('php -r "readfile(\'https://getcomposer.org/installer\');" | php');
        }
    }

    public function create()
    {

        $phar = new Phar(
            __DIR__.'/deployii.phar',
            FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME,
            'deployii.phar'
        );

        $phar->setSignatureAlgorithm(\Phar::SHA1);

        $phar->startBuffering();

        $this->_addDirectories(
            $phar,
            $this->_sourceDir,
            ['commands', 'config', 'home-dist', 'lib']
        );

        $phar->addEmptyDir('runtime');

        $this->_addVendorFiles($phar);

        // replace inclusion of console.php configuration file with console-phar.php file;
        // the phar specific configuration file disables the logger and the cache because
        // chmod (used by those components) doesn't works for files inside a phar
        $deploYiiScript = file_get_contents($this->_sourceDir.DIRECTORY_SEPARATOR.'deployii');
        $deploYiiScript = str_replace('config/console.php', 'config/console-phar.php', $deploYiiScript);
        $phar->addFromString('deployii', $deploYiiScript);

        // temporary fix to avoid the use of realpath by yii\base\Module class (untested!)
        $baseModule = file_get_contents('phar://deployii.phar/vendor/yiisoft/yii2/base/Module.php');
        $baseModule = str_replace('$p = realpath($path);', '$p = $path;', $baseModule);
        file_put_contents('phar://deployii.phar/vendor/yiisoft/yii2/base/Module.php', $baseModule);

        $phar->setStub(file_get_contents('stub.php'));
        $phar->stopBuffering();

        $phar->addFile($this->_sourceDir.DIRECTORY_SEPARATOR.'LICENSE', 'LICENSE');

        unset($phar);

        echo "\n\n[!] Note: the generated phar file doens't fully work and it is not tested.\n\n";

        if (self::DEBUG) {
            $phar = new Phar(__DIR__.'/deployii.phar');
            $phar->extractTo(__DIR__.'/ext', null, true);
        }
    }

    /**
     * @param Phar   $phar
     * @param string $baseDir
     * @param array  $directories
     */
    private function _addDirectories(\Phar $phar, $baseDir, $directories)
    {

        foreach ($directories as $dirRelPath) {

            $dir = $baseDir.DIRECTORY_SEPARATOR.$dirRelPath;

            $phar->buildFromIterator(
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
                ),
                $baseDir
            );
        }
    }

    /**
     * @param Phar   $phar
     * @param string $fileRelPath
     */
    private function _addFile(\Phar $phar, $fileRelPath)
    {
        $phar->addFile($this->_sourceDir.DIRECTORY_SEPARATOR.$fileRelPath, $fileRelPath);
    }

    /**
     * Add vendor files without the unneeded dependencies
     *
     * @param Phar $phar
     */
    private function _addVendorFiles(\Phar $phar)
    {

        $tempDir = __DIR__.DIRECTORY_SEPARATOR.'temp_'.time();
        $jsonFile = $tempDir.DIRECTORY_SEPARATOR.'composer.json';

        FileHelper::createDirectory($tempDir);

        copy($this->_sourceDir.DIRECTORY_SEPARATOR.'composer.json', $jsonFile);

        $composer = Json::decode(file_get_contents($jsonFile));

        // Remove dev and unneeded dependencies:
        $composer['replace'] = [
            "ezyang/htmlpurifier" => "*",
            "yiisoft/jquery"      => "*",
            "yiisoft/jquery-pjax" => "*",
            "cebe/markdown"       => "*",
        ];
        unset($composer['require-dev']);
        unset($composer['suggest']);
        unset($composer['scripts']);

        file_put_contents($jsonFile, Json::encode($composer));

        exec('cd '.$tempDir.' && php ../composer.phar update --prefer-dist');
        exec('cd '.$tempDir.' && php ../composer.phar dump-autoload --optimize');

        $phar->buildFromIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $tempDir.DIRECTORY_SEPARATOR.'vendor',
                    FilesystemIterator::SKIP_DOTS
                )
            ),
            $tempDir
        );

        FileHelper::removeDirectory($tempDir);
    }

}

$createPhar = new CreatePhar();
$createPhar->create();