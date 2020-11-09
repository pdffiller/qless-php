<?php

namespace Qless\Jobs;

use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;
use Qless\Exceptions\InvalidArgumentException;

/**
 * Qless\Jobs\JobFactory
 *
 * @package Qless\Jobs
 */
final class JobFactory implements EventsManagerAwareInterface
{
    use EventsManagerAwareTrait;

    /**
     * Creates the custom job instance.
     *
     * @param  string $className
     * @param  string $performMethod
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function create(string $className, string $performMethod = 'perform')
    {
        if (class_exists($className) === false) {
            throw new InvalidArgumentException("Could not find job class {$className}.");
        }

        if (method_exists($className, $performMethod) === false) {
            throw new InvalidArgumentException(
                sprintf(
                    'Job class "%s" does not contain perform method "%s".',
                    $className,
                    $performMethod
                )
            );
        }

        $instance = new $className;

        if ($instance instanceof EventsManagerAwareInterface) {
            $instance->setEventsManager($this->getEventsManager());
        }

        return $instance;
    }
}
