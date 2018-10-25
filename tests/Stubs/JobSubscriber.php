<?php

namespace Qless\Tests\Stubs;

use Qless\Events\User\Job\AbstractJobEvent;

/**
 * Qless\Tests\Stubs\JobSubscriber
 *
 * @package Qless\Tests\Stubs
 */
class JobSubscriber
{
    private $status;

    public function __construct(\stdClass $status)
    {
        $this->status = $status;
    }

    /**
     * @param AbstractJobEvent $event
     */
    public function beforePerform(AbstractJobEvent $event): void
    {
        $event->getSource()->data['stack'][] = __METHOD__;

        $this->status->triggered[] = [
            'event' => $event::getName(),
            'jid'   => $event->getSource()->jid,
        ];
    }

    /**
     * @param AbstractJobEvent $event
     */
    public function afterPerform(AbstractJobEvent $event): void
    {
        $event->getSource()->data['stack'][] = __METHOD__;

        $this->status->triggered[] = [
            'event' => $event::getName(),
            'jid'   => $event->getSource()->jid,
        ];
    }
}
