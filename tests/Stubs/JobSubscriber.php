<?php

namespace Qless\Tests\Stubs;

use Qless\Events\UserEvent;
use Qless\Jobs\Job;
use Qless\Jobs\PerformAwareInterface;

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
     * @param UserEvent $event
     * @param Job|PerformAwareInterface $source
     */
    public function beforePerform(UserEvent $event, $source): void
    {
        $source->data['stack'][] = __METHOD__;

        $this->status->triggered[] = [
            'event' => $event->getType(),
            'jid'   => $source->jid,
        ];
    }

    /**
     * @param UserEvent $event
     * @param Job|PerformAwareInterface $source
     */
    public function afterPerform(UserEvent $event, $source): void
    {
        $source->data['stack'][] = __METHOD__;

        $this->status->triggered[] = [
            'event' => $event->getType(),
            'jid'   => $source->jid,
        ];
    }
}
