<?php

namespace Qless\Tests\Stubs;

use Qless\Jobs\Job;
use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;
use Qless\Events\UserEvent;

/**
 * Qless\Tests\Stubs\EventsDrivenJobHandler
 *
 * @package Qless\Tests\Stubs
 */
class EventsDrivenJobHandler implements EventsManagerAwareInterface
{
    use EventsManagerAwareTrait;

    public function __construct()
    {
        $_SERVER['caller'] = ['stack' => []];
    }

    public function setUp()
    {
        $this->getEventsManager()->attach(
            'job:beforePerform',
            function (UserEvent $event, EventsDrivenJobHandler $source) {
                $_SERVER['caller']['stack'][] = $event->getData()[0] . ':' . $event->getType();
            }
        );

        $this->getEventsManager()->attach(
            'job:afterPerform',
            function (UserEvent $event, EventsDrivenJobHandler $source) {
                $_SERVER['caller']['stack'][] = $event->getData()[0] . ':' . $event->getType();
            }
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param Job $job
     * @return void
     */
    public function perform(Job $job): void
    {
        $_SERVER['caller']['stack'][] = "{$job->jid}:perform";

        $job->complete();
    }
}
