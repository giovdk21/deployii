<?php
/**
 * DeploYii - DeploYiiLogHandler
 *
 * Allow log messages to be stored in memory to be processed later by the build script
 * or the project deployment.
 *
 * For example we can send a mail with the log only if specified in the current project / workspace
 * settings.
 * In the same way we can for example decide to send log messages to HipChat only if the setting is
 * enabled for the current project.
 * The log sent via email could be formatted in HTML while the one sent to HipChat could be in plain text.
 *
 *
 * @link      https://github.com/giovdk21/deployii
 * @copyright Copyright (c) 2014 Giovanni Derks
 * @license   https://github.com/giovdk21/deployii/blob/master/LICENSE
 */

namespace app\lib;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;

class DeploYiiLogHandler extends AbstractHandler
{

    /** @var array list of stored logged records */
    protected $buffer = [];

    /**
     * @inheritdoc
     */
    public function handle(array $record)
    {
        if ($record['level'] < $this->level) {
            return false;
        }

        if ($this->processors) {
            foreach ($this->processors as $processor) {
                $record = call_user_func($processor, $record);
            }
        }

        $this->buffer[] = $record;

        return false === $this->bubble;
    }

    public function flush()
    {
        $this->buffer = [];
    }

    /**
     * @param FormatterInterface $formatter
     *
     * @return array
     */
    public function getFormattedLogArray($formatter = null)
    {
        $res = [];

        if ($formatter === null) {
            $formatter = new LineFormatter();
        }

        foreach ($this->buffer as $record) {
            $res[] = trim($formatter->format($record));
        }

        return $res;
    }

    /**
     * @param FormatterInterface $formatter
     *
     * @return string
     */
    public function getFormattedLog($formatter = null)
    {
        return implode("\n", $this->getFormattedLogArray($formatter))."\n";
    }

    /**
     * @param HandlerInterface $handler
     * @param bool $flush
     */
    public function sendToHandler($handler, $flush = false)
    {
        $handler->handleBatch($this->buffer);

        if ($flush) {
            $this->flush();
        }
    }

}