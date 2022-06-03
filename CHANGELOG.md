# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [3.19.0] - 2022-06-03
### Changes
- Support redis>=6.2.7

## [3.18.0] - 2022-04-25
### Fixes
- Fix (offset, count) parameters on queue.work.peek

## [3.17.2] - 2022-04-05
### Changes
- Make use ramsey/uuid also v3.9

## [3.17.1] - 2021-12-06
### Fixed
- Removed possibility to create a empty tag

## [3.17.0] - 2021-11-11
### Added
- Method for getting all tags

## [3.16.0] - 2021-05-31
### Added
- Simple worker

## [3.15.0] - 2021-05-21
### Added
- `\Qless\Client::disconnect` method to close Redis connection

### Changed
- PHP8 support
- Bump `monolog/monolog` version to `^2.0`
- Bump `ramsey/uuid` version to `^4.1`
- Bump `phpstan/phpstan` version `^0.12.87`
- Bump `phpunit/phpunit` version to `^8.5`

## [3.14.1] - 2021-05-19
### Added
- Added additional check of type in Lua

## [3.14.0] - 2021-02-22
### Added
- Jobs Collection

### Changed
- Change heartbeat signature to match other binding defaults

### Fixed
- Fixed count failed jobs after removing

## [3.13.0] - 2020-12-23
### Added
 - TTL for failed jobs

### Changed
 - Recur JID is consistent with normal JID

## [3.12.1] - 2020-11-21
### Fixed
- Fix nil state of job 

## [3.12.0] - 2020-11-09
### Added
 - Add support for forgetting a queue
### Fixed
 - Cleanup a heap of small code quality issues
 - Cleanup unit tests 

## [3.11.1] - 2020-08-28
### Fixed
 - Add syncCompleteEvent only if needed
 
## [3.11.0] - 2020-04-15
### Added
 - Added PubSub Manager
### Fixed
 - Force check worker variable type

## [3.10.1] - 2019-11-25
### Fixed
 - Clear old data of removed tracked jobs 

## [3.10.0] - 2019-11-06
### Added
 - Added getting queues by priority range
 
## [3.9.1] - 2019-08-08
### Fixed
-fixed stop worker if limit time is reached after running

## [3.9.0] - 2019-08-07
### Added
- Added limits for workers by memory, execution time and tasks count.

## [3.8.3] - 2019-07-19
### Fixed
- Fixed removing completed jobs history

## [3.8.2] - 2019-07-05
### Fixed
- Fire event BeforeFork before fork
- Added deregister worker for SIGQUIT
- Fix method name beforeFirstWork
 
## [3.8.1] - 2019-05-07
### Added
- Added getting workers by range.
- Added getting total count of registered workers.

## [3.8.0] - 2019-04-24
### Added
- Added getter of tracked jobs list.
- Added remove worker feature.

## [3.7.0] - 2019-04-19
### Added
- Added state getter to a job. 

## [3.6.1] - 2019-04-09
### Fixed
- Fixed bug with multi-subscription for a topic.

## [3.6.0] - 2019-04-02
### Added
- Added possibility to get jobs in worker (all jobs or by time filter).
- Added possibility to get jobs in status waiting.
- Added possibility to get jobs in status completed by queue.
### Changed
- Check current job status before change it in Redis
### Fixed
- Fixed bug on parameters order in 'job' method.
- Removed possibility to get a job by id twice

## [3.5.0] - 2019-02-08
### Added
- Added possibility for synchronously job handle.
- Added possibility for push to empty subscribers (queues).
- Added failure getter to a job 

## [3.4.0] - 2019-01-10
### Added
- Added failed flag. 

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

[Unreleased]: https://github.com/pdffiller/qless-php/compare/v3.19.0...HEAD
[3.19.0]: https://github.com/pdffiller/qless-php/compare/v3.18.2...v3.19.0
[3.18.0]: https://github.com/pdffiller/qless-php/compare/v3.17.2...v3.18.0
[3.17.2]: https://github.com/pdffiller/qless-php/compare/v3.17.1...v3.17.2
[3.17.1]: https://github.com/pdffiller/qless-php/compare/v3.17.0...v3.17.1
[3.17.0]: https://github.com/pdffiller/qless-php/compare/v3.16.0...v3.17.0
[3.16.0]: https://github.com/pdffiller/qless-php/compare/v3.15.0...v3.16.0
[3.15.0]: https://github.com/pdffiller/qless-php/compare/v3.14.1...v3.15.0
[3.14.1]: https://github.com/pdffiller/qless-php/compare/v3.14.0...v3.14.1
[3.14.0]: https://github.com/pdffiller/qless-php/compare/v3.13.0...v3.14.0
[3.13.0]: https://github.com/pdffiller/qless-php/compare/v3.12.1...v3.13.0
[3.12.1]: https://github.com/pdffiller/qless-php/compare/v3.12.0...v3.12.1
[3.12.0]: https://github.com/pdffiller/qless-php/compare/v3.11.1...v3.12.0
[3.11.1]: https://github.com/pdffiller/qless-php/compare/v3.11.0...v3.11.1
[3.11.0]: https://github.com/pdffiller/qless-php/compare/v3.10.1...v3.11.0
[3.10.1]: https://github.com/pdffiller/qless-php/compare/v3.10.0...v3.10.1
[3.10.0]: https://github.com/pdffiller/qless-php/compare/v3.9.1...v3.10.0
[3.9.1]: https://github.com/pdffiller/qless-php/compare/v3.9.0...v3.9.1
[3.9.0]: https://github.com/pdffiller/qless-php/compare/v3.8.3...v3.9.0
[3.8.3]: https://github.com/pdffiller/qless-php/compare/v3.8.2...v3.8.3
[3.8.2]: https://github.com/pdffiller/qless-php/compare/v3.8.1...v3.8.2
[3.8.1]: https://github.com/pdffiller/qless-php/compare/v3.8.0...v3.8.1
[3.8.0]: https://github.com/pdffiller/qless-php/compare/v3.7.0...v3.8.0
[3.7.0]: https://github.com/pdffiller/qless-php/compare/v3.6.1...v3.7.0
[3.6.1]: https://github.com/pdffiller/qless-php/compare/v3.6.0...v3.6.1
[3.6.0]: https://github.com/pdffiller/qless-php/compare/v3.5.0...v3.6.0
[3.5.0]: https://github.com/pdffiller/qless-php/compare/v3.4.0...v3.5.0
[3.4.0]: https://github.com/pdffiller/qless-php/compare/v3.3.0...v3.4.0
[3.3.0]: https://github.com/pdffiller/qless-php/compare/v3.2.0...v3.3.0
[3.2.0]: https://github.com/pdffiller/qless-php/compare/v3.1.0...v3.2.0
[3.1.0]: https://github.com/pdffiller/qless-php/compare/v3.0.0...v3.1.0
[3.0.0]: https://github.com/pdffiller/qless-php/compare/v2.2.1...v3.0.0
[2.2.1]: https://github.com/pdffiller/qless-php/compare/v2.2.0...v2.2.1
[2.2.0]: https://github.com/pdffiller/qless-php/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/pdffiller/qless-php/compare/v2.0.1...v2.1.0
[2.0.1]: https://github.com/pdffiller/qless-php/compare/2.0.0...v2.0.1
[2.0.0]: https://github.com/pdffiller/qless-php/compare/v1.0.0...2.0.0
