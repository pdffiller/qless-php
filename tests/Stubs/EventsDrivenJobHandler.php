<?php

namespace Qless\Tests\Stubs;

use Qless\Events\User\Job as JobEvent;
use Qless\Jobs\BaseJob;
use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;
use Qless\Jobs\PerformAwareInterface;

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

    public function setUp(): void
    {
        $this->getEventsManager()->attach(
            JobEvent\BeforePerform::getName(),
            function (JobEvent\AbstractJobEvent $event) {
                $_SERVER['caller']['stack'][] = $event->getJob()->jid . ':' . $event->getName();
            }
        );

        $this->getEventsManager()->attach(
            JobEvent\AfterPerform::getName(),
            function (JobEvent\AbstractJobEvent $event) {
                $_SERVER['caller']['stack'][] = $event->getJob()->jid. ':' . $event->getName();
            }
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param BaseJob $job
     * @return void
     */
    public function perform(BaseJob $job): void
    {
        $_SERVER['caller']['stack'][] = "{$job->jid}:job:perform";

        $job->complete();
    }
}
