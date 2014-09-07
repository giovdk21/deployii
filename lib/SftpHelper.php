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

    /** @var string the connection string; see http://php.net/manual/en/wrappers.php */
    private $_connString = '';

    /** @var int the last exit status returned by the exe command */
    private $_lastExecExitStatus = 0;

    /**
     * @param string $connId           the connection ID
     * @param mixed  $connection       the FTP or Net_SFTP connection returned by $controller->getConnection
     * @param array  $connectionParams the connection parameters
     */
    public function __construct($connId, $connection, $connectionParams)
    {
        $this->_connId = $connId;
        $this->_connection = $connection;
        $this->_setConnectionType($connectionParams['sftpConnectionType']);
        $this->_connString = $this->_getConnectionString($connectionParams);
    }

    /**
     * Set and verify the connection type
     *
     * @param string $type
     */
    private function _setConnectionType($type)
    {

        if ($type !== self::TYPE_SFTP && $type !== self::TYPE_FTP) {
            Console::stdout("\n");
            Log::throwException('Unsupported connection type: '.$type);
        }

        $this->_connType = $type;
    }

    /**
     * @param array $connectionParams the connection parameters
     *
     * @return string the connection string
     */
    private function _getConnectionString(array $connectionParams)
    {
        return $this->_connType.'://'
        .$connectionParams['sftpUsername'].':'.$connectionParams['sftpPassword']
        .'@'.$connectionParams['sftpHost'].':'.$connectionParams['sftpPort'];
    }

    /**
     * Returns the full stream url (connection string + absolute path)
     *
     * @param string $path
     *
     * @return string
     */
    private function _getStreamUrl($path)
    {
        if (substr($path, 0, 1) !== '/') {
            return $this->_connString.$this->pwd().'/'.$path;
        } else {
            return $this->_connString.$path;
        }
    }

    /**
     * @param string $function name of the method executed to get the result
     * @param string $key      unique identifier of the method parameters
     * @param mixed  $value    the retrieved value
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
     * @param string $key      unique identifier of the method parameters
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

        clearstatcache();
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

            switch ($this->_connType) {

                case SftpHelper::TYPE_SFTP:
                default:
                    $res = $this->_connection->file_exists($path);
                    break;

                case SftpHelper::TYPE_FTP:
                    $res = file_exists($this->_getStreamUrl($path));
                    break;
            }

            Log::logger()->addDebug(
                'Performing '.__METHOD__.' // result: {res}',
                ['res' => $res, 'path' => $path]
            );

            $this->_addToCache(__FUNCTION__, $path, $res);
        }

        return $res;
    }

    /**
     * Returns the stat information of the given path.
     * It also sets the cache for the fileExists command.
     *
     * NOTE: the returned array structure depends on the current connection type
     *
     * @param string $path
     *
     * @return false|array
     */
    public function stat($path)
    {
        $stat = $this->_getFromCache(__FUNCTION__, $path);
        if ($stat === null) {

            switch ($this->_connType) {

                case SftpHelper::TYPE_SFTP:
                default:
                    $stat = $this->_connection->stat($path);
                    break;

                case SftpHelper::TYPE_FTP:
                    $stat = false;
                    if ($this->fileExists($path)) {
                        $stat = stat($this->_getStreamUrl($path));
                    }
                    break;
            }

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
     *
     * @return bool
     */
    public function isDir($path)
    {
        $res = $this->_getFromCache(__FUNCTION__, $path);

        if ($res === null) {

            switch ($this->_connType) {

                case SftpHelper::TYPE_SFTP:
                default:
                    $res = $this->_connection->is_dir($path);
                    break;

                case SftpHelper::TYPE_FTP:
                    $res = is_dir($this->_getStreamUrl($path));
                    break;
            }

            $this->_addToCache(__FUNCTION__, $path, $res);
            if ($res) {
                $this->_addToCache('fileExists', $path, true);
            }

            Log::logger()->addDebug(
                'Performing '.__METHOD__.' // result: {res}',
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
    public function isFile($path)
    {
        $res = $this->_getFromCache(__FUNCTION__, $path);

        if ($res === null) {

            switch ($this->_connType) {

                case SftpHelper::TYPE_SFTP:
                default:
                    $res = $this->_connection->is_file($path);
                    break;

                case SftpHelper::TYPE_FTP:
                    $res = is_file($this->_getStreamUrl($path));
                    break;
            }

            $this->_addToCache(__FUNCTION__, $path, $res);
            if ($res) {
                $this->_addToCache('fileExists', $path, true);
            }

            Log::logger()->addDebug(
                'Performing '.__METHOD__.' // result: {res}',
                ['res' => $res, 'path' => $path]
            );
        }

        return $res;
    }

    /**
     * Returns the current directory path
     *
     * @return string
     */
    public function pwd()
    {

        switch ($this->_connType) {

            case SftpHelper::TYPE_SFTP:
            default:
                $res = $this->_connection->pwd();
                break;

            case SftpHelper::TYPE_FTP:
                $res = ftp_pwd($this->_connection);
                break;
        }

        return $res;
    }

    /**
     * Returns the content of the given directory
     *
     * @param string $dir
     * @param bool   $recursive
     * @param bool   $log whether to log or not the action
     *
     * @return array
     */
    public function nlist($dir, $recursive = false, $log = true)
    {
        $res = $this->_getFromCache(__FUNCTION__, $dir);

        if ($res === null) {

            switch ($this->_connType) {

                case SftpHelper::TYPE_SFTP:
                default:
                    $res = $this->_connection->nlist($dir, $recursive);
                    break;

                case SftpHelper::TYPE_FTP:
                    if ($recursive) {
                        Console::stdout("\n");
                        Log::throwException('Recursive not supported for nlist in ftp mode');
                    }

                    $res = @ftp_nlist($this->_connection, $dir);

                    if (is_array($res)) {
                        // remove the $dir prefix from the results to match the format
                        // of $this->_connection->nlist
                        $res = preg_replace('/^'.str_replace('/', '\\/', $dir).'[\\/]/', '', $res);
                    } elseif (!$res && $this->isDir($dir)) {
                        // for some reason ftp_nlist returns false if the directory is empty...
                        $res = [];
                    }
                    break;
            }

            $this->_addToCache(__FUNCTION__, $dir, $res);

            if ($log) {
                Log::logger()->addDebug(
                    'Performing '.__METHOD__.' // result: {res}',
                    ['res' => var_export($res, true), 'path' => $dir]
                );
            }
        }

        return $res;
    }

    /**
     * Create a directory.
     *
     * @param string $dir
     * @param int    $mode
     * @param bool   $recursive
     *
     * @return bool
     */
    public function mkdir($dir, $mode = -1, $recursive = false)
    {

        switch ($this->_connType) {

            case SftpHelper::TYPE_SFTP:
            default:
                $res = $this->_connection->mkdir($dir, $mode, $recursive);
                break;

            case SftpHelper::TYPE_FTP:
                $res = false;

                if ($recursive) {
                    $pathArray = explode('/', $dir);
                } else {
                    $pathArray = [$dir];
                }

                $i = 0;
                $fullDirPath = '';
                $pathArrayCount = count($pathArray);
                foreach ($pathArray as $dirPath) {
                    $fullDirPath .= (!empty($fullDirPath) ? '/' : '').$dirPath;

                    $mode = ($mode === -1 ? 0755 : $mode);
                    $res = @ftp_mkdir($this->_connection, $fullDirPath);

                    $i++;
                    if ($i === $pathArrayCount) {
                        @ftp_chmod($this->_connection, $mode, $fullDirPath);
                    }
                }
                break;
        }

        return $res;
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

        switch ($this->_connType) {

            case SftpHelper::TYPE_SFTP:
            default:
                $res = $this->_connection->rmdir($dir);
                break;

            case SftpHelper::TYPE_FTP:
                $res = @ftp_rmdir($this->_connection, $dir);
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

        switch ($this->_connType) {

            case SftpHelper::TYPE_SFTP:
            default:
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
        }

        return $res;
    }

    /**
     * @param string $remoteFile
     * @param string $localFile
     * @param int    $startPos
     *
     * @return bool
     */
    public function put($remoteFile, $localFile, $startPos = -1)
    {
        switch ($this->_connType) {

            case SftpHelper::TYPE_SFTP:
            default:
                $res = $this->_connection->put($remoteFile, $localFile, NET_SFTP_LOCAL_FILE, $startPos);
                break;

            case SftpHelper::TYPE_FTP:
                $startPos = ($startPos === -1 ? 0 : $startPos);
                $res = @ftp_put($this->_connection, $remoteFile, $localFile, FTP_BINARY, $startPos);
                break;
        }

        return $res;
    }

    /**
     * @param string $remotePath
     * @param string $destFile
     * @param int    $resumePos
     *
     * @return bool
     */
    public function get($remotePath, $destFile, $resumePos = 0)
    {
        switch ($this->_connType) {

            case SftpHelper::TYPE_SFTP:
            default:
                $res = $this->_connection->get($remotePath, $destFile, $resumePos);
                break;

            case SftpHelper::TYPE_FTP:
                $res = @ftp_get($this->_connection, $destFile, $remotePath, FTP_BINARY, $resumePos);
                break;
        }

        return $res;
    }

    /**
     * @param string $mode
     * @param string $path
     * @param bool   $recursive
     *
     * @return bool
     */
    public function chmod($mode, $path, $recursive = false)
    {
        switch ($this->_connType) {

            case SftpHelper::TYPE_SFTP:
            default:
                $res = $this->_connection->chmod($mode, $path, $recursive);
                break;

            case SftpHelper::TYPE_FTP:
                if ($recursive) {
                    Console::stdout("\n");
                    Log::throwException('Recursive not supported for chmod in ftp mode');
                }

                $res = @ftp_chmod($this->_connection, $mode, $path);
                break;
        }

        return $res;
    }

    /**
     * Rename a file or directory
     *
     * @param $oldName
     * @param $newName
     *
     * @return bool
     */
    public function rename($oldName, $newName)
    {
        switch ($this->_connType) {

            case SftpHelper::TYPE_SFTP:
            default:
                $res = $this->_connection->rename($oldName, $newName);
                break;

            case SftpHelper::TYPE_FTP:
                $res = ftp_rename($this->_connection, $oldName, $newName);
                break;
        }

        return $res;
    }

    /**
     * @param $command
     *
     * @return string
     */
    public function exec($command)
    {
        $sftpDir = $this->pwd();

        switch ($this->_connType) {

            case SftpHelper::TYPE_SFTP:
            default:
                $execOutput = $this->_connection->exec('cd '.$sftpDir.' && '.$command);
                $this->_lastExecExitStatus = $this->_connection->getExitStatus();
                break;

            case SftpHelper::TYPE_FTP:
                // TODO: test ftp_exec on a server which supports it

                $execOutput = '';
                $res = @ftp_exec($this->_connection, 'cd '.$sftpDir.' && '.$command);
                $this->_lastExecExitStatus = ($res ? 0 : 1);
                break;
        }

        return $execOutput;
    }

    /**
     * Returns the last exit status of the exec command
     *
     * @return int
     */
    public function getExecExitStatus()
    {
        return $this->_lastExecExitStatus;
    }

}