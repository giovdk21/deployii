<?php
/**
 * DeploYii - SftpHelper
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;

use Net_SFTP;
use yii\helpers\Console;

class SftpHelper
{

    const AUTH_METHOD_KEY = 'key';
    const AUTH_METHOD_PASSWORD = 'password';

    const TYPE_SFTP = 'sftp';
    const TYPE_FTP = 'ftp';

    /** @var array Save the retrieved results in a cache array */
    public static $cache = [];

    /** @var  \Net_SFTP|resource */
    private $_connection;

    /** @var string the connection id */
    private $_connId = '';

    /** @var string the connection type */
    private $_connType = '';

    /**
     * @param string $connId     the connection ID
     * @param mixed  $connection the FTP or Net_SFTP connection returned by $controller->getConnection
     * @param string $type the connection type (self::TYPE_SFTP | self::TYPE_FTP)
     */
    public function __construct($connId, $connection, $type)
    {
        $this->_connId = $connId;
        $this->_connection = $connection;
        $this->_connType = $type;
    }

    /**
     * @param string $function name of the method executed to get the result
     * @param string $key    unique identifier of the method parameters
     * @param mixed  $value  the retrieved value
     */
    private function _addToCache($function, $key, $value)
    {
        $connId = $this->_connId;

        if (!isset(self::$cache[$connId])) {
            self::$cache[$connId] = [];
        }

        if (!isset(self::$cache[$connId][$function])) {
            self::$cache[$connId][$function] = [];
        }

        self::$cache[$connId][$function][$key] = $value;
    }

    /**
     * @param string $function name of the method executed to get the result
     * @param string $key    unique identifier of the method parameters
     *
     * @return null|mixed Returns null if the method result hasn't been cashed yet
     */
    private function _getFromCache($function, $key)
    {
        $connId = $this->_connId;
        $res = null;

        if (isset(self::$cache[$connId][$function][$key])) {
            $res = self::$cache[$connId][$function][$key];
        }

        return $res;
    }

    /**
     * Delete the cache for the given connection ID and/or method
     *
     * @param string $function (optional) name of the method executed to get the result
     */
    public function flushCache($function = '')
    {
        $connId = $this->_connId;

        Log::logger()->addDebug(
            'Performing '.__METHOD__.' // function: {function}',
            ['function' => $function]
        );

        if (isset(self::$cache[$connId])) {
            if (!empty($function) && isset(self::$cache[$connId][$function])) {
                self::$cache[$connId][$function] = [];
            } elseif (empty($function)) {
                self::$cache[$connId] = [];
            }
        }
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function fileExists($path)
    {
        $res = $this->_getFromCache(__FUNCTION__, $path);

        if ($res === null) {
            $size = $this->_connection->size($path);
            Log::logger()->addDebug(
                'Performing '.__METHOD__.' // size: {size}',
                ['size' => $size, 'path' => $path]
            );
            $res = ($size !== false);
            $this->_addToCache(__FUNCTION__, $path, $res);
        }

        return $res;
    }

    /**
     * Returns the stat information of the given path.
     * It also sets the cache for the fileExists command.
     *
     * @param string $path
     *
     * @return false|array
     */
    public function stat($path)
    {
        $stat = $this->_getFromCache(__FUNCTION__, $path);
        if ($stat === null) {
            $stat = $this->_connection->stat($path);
            Log::logger()->addDebug(
                'Performing '.__METHOD__.' // result: {stat}',
                ['stat' => var_export($stat, true), 'path' => $path]
            );
            $this->_addToCache(__FUNCTION__, $path, $stat);
            $this->_addToCache('fileExists', $path, (isset($stat['size']) ? true : false));
        }

        return $stat;
    }

    /**
     * @param string $path
     * @param string $type
     *
     * @return bool
     */
    public function isType($path, $type)
    {
        $cacheKey = $path.'_'.$type;
        $res = $this->_getFromCache(__FUNCTION__, $cacheKey);

        if ($res === null) {
            $stat = $this->stat($path);

            $typeVal = -1;
            switch ($type) {
                case 'file':
                    /** @noinspection PhpUndefinedConstantInspection */
                    $typeVal = NET_SFTP_TYPE_REGULAR;
                    break;
                case 'dir':
                    /** @noinspection PhpUndefinedConstantInspection */
                    $typeVal = NET_SFTP_TYPE_DIRECTORY;
                    break;
            }

            $res = ($stat && $stat['type'] === $typeVal);
            $this->_addToCache(__FUNCTION__, $cacheKey, $res);

            Log::logger()->addDebug(
                'Performing '.__METHOD__.' ('.$type.') // result: {res}',
                ['res' => $res, 'path' => $path]
            );
        }

        return $res;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function isDir($path)
    {
        return $this->isType($path, 'dir');
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function isFile($path)
    {
        return $this->isType($path, 'file');
    }

    /**
     * Removes an empty directory.
     *
     * @param string $dir
     *
     * @return bool
     */
    public function rmdir($dir)
    {
        $res = false;

        switch ($this->_connType) {

            case SftpHelper::TYPE_SFTP:
                $res = $this->_connection->rmdir($dir);
                break;

            case SftpHelper::TYPE_FTP:
                $res = @ftp_rmdir($this->_connection, $dir);
                break;

            default:
                Console::stdout("\n");
                Log::throwException('Unsupported connection type: '.$this->_connType);
                break;
        }

        return $res;
    }

    /**
     * Deletes a file or directory on the SFTP/FTP server.
     *
     * @param string $path
     * @param bool   $recursive if $path is a directory, it will delete also its content
     *
     * @return bool
     */
    public function delete($path, $recursive = true)
    {
        $res = false;

        switch ($this->_connType) {

            case SftpHelper::TYPE_SFTP:
                $res = $this->_connection->delete($path, $recursive);
                break;

            case SftpHelper::TYPE_FTP:
                $res = @ftp_delete($this->_connection, $path);

                if (!$res && $recursive) {
                    $list = @ftp_nlist($this->_connection, $path);

                    // if $path exists and it is a directory:
                    if (!empty($list)) {
                        foreach ($list as $file) {
                            $this->delete($file, true);
                        }

                        // deleting the parent path after the recursion
                        $res = $this->delete($path, false);
                    }
                }

                break;

            default:
                Console::stdout("\n");
                Log::throwException('Unsupported connection type: '.$this->_connType);
                break;
        }

        return $res;
    }

} 