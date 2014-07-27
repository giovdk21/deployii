<?php
/**
 * DeploYii - Log
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use yii\console\Exception;
use yii\helpers\FileHelper;

class Log
{

    /** @var Logger the logger object */
    private static $_logger;

    public static $logFile = '';

    /** @var DeploYiiLogHandler */
    public static $deployiiHandler;

    /**
     * Returns the logger object and instantiate it if needed
     *
     * @return Logger the logger object
     */
    public static function logger()
    {
        if (empty(self::$_logger)) {
            self::_initLogger();
        }

        return self::$_logger;
    }

    /**
     * Initialise the logger
     *
     * By default a log is saved to file into the @home/log folder and
     * the log messages are stored by DeploYiiLogHandler to be used later.
     */
    private static function _initLogger()
    {

        $logDir = Shell::getHomeDir().DIRECTORY_SEPARATOR.'log';
        self::$logFile = $logDir.DIRECTORY_SEPARATOR.date('Ymd_His').uniqid().'.log';

        if (!is_dir($logDir)) {
            FileHelper::createDirectory($logDir);
        }

        self::$_logger = new Logger('main');

        $streamHandler = new StreamHandler(self::$logFile, Logger::DEBUG);
        /** @noinspection PhpParamsInspection */
        $streamHandler->pushProcessor(new PsrLogMessageProcessor);

        self::$deployiiHandler = new DeploYiiLogHandler();
        /** @noinspection PhpParamsInspection */
        self::$deployiiHandler->pushProcessor(new PsrLogMessageProcessor);

        self::$_logger->pushHandler($streamHandler);
        self::$_logger->pushHandler(self::$deployiiHandler);
    }

    /**
     * Log the error message and throw \yii\console\Exception
     *
     * @param string $message
     *
     * @throws \yii\console\Exception
     */
    public static function throwException($message)
    {
        Log::logger()->addCritical($message);
        throw new Exception($message);
    }


} 