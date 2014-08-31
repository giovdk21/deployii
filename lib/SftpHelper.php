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
     * @param string $method name of the method executed to get the result
     * @param string $key    unique identifier of the method parameters
     * @param mixed  $value  the retrieved value
     */
    private function _addToCache($method, $key, $value)
    {
        $connId = $this->_connId;

        if (!isset(self::$cache[$connId])) {
            self::$cache[$connId] = [];
        }

        if (!isset(self::$cache[$connId][$method])) {
            self::$cache[$connId][$method] = [];
        }

        self::$cache[$connId][$method][$key] = $value;
    }

    /**
     * @param string $method name of the method executed to get the result
     * @param string $key    unique identifier of the method parameters
     *
     * @return null|mixed Returns null if the method result hasn't been cashed yet
     */
    private function _getFromCache($method, $key)
    {
        $connId = $this->_connId;
        $res = null;

        if (isset(self::$cache[$connId][$method][$key])) {
            $res = self::$cache[$connId][$method][$key];
        }

        return $res;
    }

    /**
     * Delete the cache for the given connection ID and/or method
     *
     * @param string $method (optional) name of the method executed to get the result
     */
    public function flushCache($method = '')
    {
        $connId = $this->_connId;

        Log::logger()->addDebug(
            'Performing '.__METHOD__.' // method: {method}',
            ['method' => $method]
        );

        if (isset(self::$cache[$connId])) {
            if (!empty($method) && isset(self::$cache[$connId][$method])) {
                self::$cache[$connId][$method] = [];
            } elseif (empty($method)) {
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
                ['size' => $size]
            );
            $res = ($size !== false);
            $this->_addToCache(__FUNCTION__, $path, $res);
        }

        return $res;
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
            $stat = $this->_getFromCache('stat', $path);
            if ($stat === null) {
                $stat = $this->_connection->stat($path);
                Log::logger()->addDebug(
                    'Performing connection->stat // result: {stat}',
                    ['stat' => var_export($stat, true)]
                );
                $this->_addToCache('stat', $path, $stat);
            }

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
        $res = $this->isType($path, 'dir');

        Log::logger()->addDebug(
            'Performing '.__METHOD__.' // result: {res}',
            ['res' => $res]
        );

        return $res;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function isFile($path)
    {
        $res = $this->isType($path, 'file');

        Log::logger()->addDebug(
            'Performing '.__METHOD__.' // result: {res}',
            ['res' => $res]
        );

        return $res;
    }

} 