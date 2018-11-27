# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]


## [3.3.0] - 2018-11-27
### Added
- Added subscription to topics for queues. 

## [3.2.0] - 2018-11-23
### Added
- Added `PriorityReserver` which orders queues by its priority.

## [3.1.0] - 2018-11-06
### Added
- Added the `DefaultReserver` which works just like `OrderedReserver` with one exception: it does not sort the queues
- Added the `BeforeEnqueue` event support
- Added the `Qless\Subscribers\WatchdogSubscriber` subscriber to watching events for a job
- Added the `Qless\SystemFacade` facade to to easily mock system functions

### Changed
- Qless now use `predis/predis` as an internal Redis client. The `redis` extension no longer required

### Removed
- Removed no longer used internal classes:
  - `Qless\Events\QlessCoreEventFactory`
  - `Qless\Subscribers\QlessCoreSubscriber`
  - `Qless\Redis`

### Fixed
- Fixed  `OrderedReserver` to sort queues using natural ordering like [`natsort()`](http://php.net/manual/en/function.natsort.php)

## [3.0.0] - 2018-10-19
### Changed
- Brand new Events Model:
  - `Qless\Events\UserEvent` replaced with bundle of classes to represent all possible events.
  - Event handlers started to receive only one argument - instance of corresponding event class.   

### Removed
- Removed qlessd script

### Fixed
- `gethostname()` doesn't work properly (or at least always) on Amazon's EC2 thus it replaced by `php_uname('n')`

## [2.2.1] - 2018-10-09
### Fixed
- Fixed command line arguments parsing for qlessd and improved error reporting

## [2.2.0] - 2018-10-09
### Added
- All qless exception now implements `Qless\Exceptions\ExceptionInterface`
- Added getters and setters for those classes that used `__get` and `__set` to access their properties
- Now the `WorkerInterface` and the `ReserverInterface` extends `Psr\Log\LoggerAwareInterface` so that
  we can transparently share logger between worker and reserver
- If the reserver was created using the specification  (regexp), it will receive an up-to-date list of queues
  each time the `ReserverInterface::reserve()` was called

### Changed
- Changed the reserver's constructor interface so that it became possible to create an instance using:
  - An array of the queue names
  - A string representing the queue name
  - A queue search specification (regexp)

### Fixed
- `Qless\Jobs\Reservers\OrderedReserver` will receive a list of queues in sorted order
  (previously in the order in which they were added)

## [2.1.0] - 2018-10-08
### Added
- Added Nginx example

### Changed
- Changed `\Qless\Workers\AbstractWorker::registerJobPerformHandler` method. Now this method is used to inject pre-existing
 Job Handler to Worker.

## [2.0.1] - 2018-10-05
### Fixed
- Fixed `ForkingWorker::perform` by adding missed `declare(ticks=1)`

## [2.0.0] - 2018-10-02

### Added
- Introduced `Qless\Jobs\JobHandlerInterface` so that `Worker::registerJobPerformHandler` will require
  that its argument is implements the JobHandlerInterface interface
- Added all the required PHP extensions to the Composer's `require` section so that
  now Composer will check dependencies on library installation time
- Added support of default Worker's `perform` method
- Added `Qless\Events\QlessCoreEvent` DTO and `Qless\Events\QlessCoreEventFactory` to interact with qless-core events
- Added `Qless\Client::getWorkerName` to provide default worker name
- Added ability to select the redis database
- Added the `Qless\Queues\Collection` for accessing queues lazily
- Added the `Qless\Workers\Collection` for accessing workers lazily
- Workers now can set/get its own name via `setName`/`getName`
- Added job reservers (ordered, round robin, shuffled round robin)
- Added `Qless\Jobs\RecurringJob` to wrap recurring jobs
- Added basic event system
- Added initial `qlessd` daemon
- Added ability to adjust a job's priority while it's still waiting in a queue
- Added `Qless\Jobs\Collection::tagged` to fetches a list of tagged JIDs associated with provided tag
- Added `Qless\Jobs\BaseJob::track` and `Qless\Jobs\BaseJob::untrack` to (un)flagging a job as important
- Added `Qless\Queues\Collection::fromSpec` to fetch a queues list using regular expression

### Changed
- PHP 5.x no longer supported. Minimal required version is 7.1
- Updated qless-core
- Move `Qless\Lua` to the `Qless\LuaScript`
- Move `Qless\Listener` to the `Qless\Subscribers\QlessCoreSubscriber`
- Move `Qless\Job` to the `Qless\Jobs\BaseJob`
- Move `Qless\Jobs` to the `Qless\Jobs\Collection`
- Move `Qless\Worker` to the `Qless\Workers\ForkingWorker`
- Move `Qless\Queue` to the `Qless\Queues\Queue`
- More code quality improvements to follow the SRP. Thus the code base for almost all classes has been changed
- Move all the exceptions to the common `Qless\Exceptions` namespace
- Changed `Qless\Queues\Queue::put` signature from the `put($className, $jid, $data, ...)`
  to the `put(string className, array $data, ?string $jid = null, ...)`
- Now `Qless\Queues\Queue::pop` does not require the mandatory presence of the worker name as its 1st argument.
  If the the worker name is not passed the `Qless\Client::getWorkerName` will be used
- Now calling `Qless\Queues\Queue::pop` without 2nd argument (number of jobs to pop off of the queue) will return
  `Qless\Jobs\BaseJob|null` so that there is no need to play with arrays like `$job[0]->function()`
- Rework job classes to remove no longer needed getters
- Changed signature of the `Qless\Jobs\BaseJob::requeue` from `requeue(array $opts = []): string` to
  `requeue(?string $queue = null, array $opts = []): string` so that there is an ability to move job to a new queue
- Changed signature of the `Qless\Queues\Queue::put` so that `resources`, `replace` and `interval` no longer used 
- Changed signature of the `Qless\Queues\Queue::recur` from
  ```php
  Queue::recur(
      $klass,
      $jid,
      $data,
      $interval = 0,
      $offset = 0,
      $retries = 5,
      $priority = 0,
      $resources = [],
      $tags = []
  )
  ```
  to
  ```php
  Queue::recur(
      string $className,
      array $data,
      ?int $interval = null,
      ?int $offset = null,
      ?string $jid = null,
      ?int $retries = null,
      ?int $priority = null,
      ?int $backlog = null,
      ?array $tags = null
  )
  ```
  to follow actual qless-core API

### Removed
- Fully refactor the `Qless\Client` class and removed no longer used code
- Removed no longer required `Qless\Qless::fork` and `pcntl_fork` check
- Removed no longer needed `Qless\Qless` class
- Removed no longer needed `Qless\Job::fromJobData`
- Removed no longer supported by qless-core `Qless\Resource`, `Qless\Job::getResources` and `Qless\Job::getInterval`
- The `Qless\Client::paused` no longer provided by qless-core (but we're saved `Qless\Queues\Queue::isPaused`)

## 1.0.0 - 2018-08-30
### Added
 - Initial stable release

[Unreleased]: https://github.com/pdffiller/qless-php/compare/v3.1.0...HEAD
[3.1.0]: https://github.com/pdffiller/qless-php/compare/v3.0.0...v3.1.0
[3.0.0]: https://github.com/pdffiller/qless-php/compare/v2.2.1...v3.0.0
[2.2.1]: https://github.com/pdffiller/qless-php/compare/v2.2.0...v2.2.1
[2.2.0]: https://github.com/pdffiller/qless-php/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/pdffiller/qless-php/compare/v2.0.1...v2.1.0
[2.0.1]: https://github.com/pdffiller/qless-php/compare/2.0.0...v2.0.1
[2.0.0]: https://github.com/pdffiller/qless-php/compare/v1.0.0...2.0.0
