<?php

namespace Qless\Events\User\Queue;

use Qless\Jobs\JobData;

/**
 * Qless\Events\User\Queue\BeforeEnqueue
 *
 * @package Qless\Events\User\Queue
 */
class BeforeEnqueue extends AbstractQueueEvent
{
    private $jid;
    private $data;
    private $className;

    /**
     * BeforeEnqueue constructor.
     *
     * @param object $source
     * @param string $jid
     * @param JobData  $data
     * @param string $className
     */
    public function __construct($source, string $jid, JobData $data, string $className)
    {
        parent::__construct($source);

        $this->jid = $jid;
        $this->data = $data;
        $this->className = $className;
    }

    public static function getHappening(): string
    {
        return 'beforeEnqueue';
    }

    public function getJid(): string
    {
        return $this->jid;
    }

    public function getData(): JobData
    {
        return $this->data;
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}
