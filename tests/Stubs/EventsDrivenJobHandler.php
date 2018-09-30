<?php

namespace Qless\Tests\Stubs;

use Qless\Jobs\BaseJob;
use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;
use Qless\Events\UserEvent;
use Qless\Jobs\PerformAwareInterface;
use Qless\Jobs\RecurringJob;

/**
 * Qless\Tests\Stubs\EventsDrivenJobHandler
 *
 * @package Qless\Tests\Stubs
 */
class EventsDrivenJobHandler implements EventsManagerAwareInterface, PerformAwareInterface
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
     * @param BaseJob|RecurringJob $job
     * @return void
     */
    public function perform(BaseJob $job): void
    {
        $_SERVER['caller']['stack'][] = "{$job->jid}:perform";

        $job->complete();
    }
}
