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

class SftpHelper
{

    /** @var array Save the retrieved results in a cache array */
    public static $cache = [];

    /** @var  \Net_SFTP */
    private $_connection;

    private $_connId;

    /**
     * @param string $connId     the connection ID
     * @param mixed  $connection the FTP or Net_SFTP connection returned by $controller->getConnection
     */
    public function __construct($connId, $connection)
    {
        $this->_connId = $connId;
        $this->_connection = $connection;
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

} 