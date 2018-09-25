# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

### Added
- Introduced `Qless\Jobs\JobHandlerInterface` so that `Worker::registerJobPerformHandler` will require
  that its argument is implements the JobHandlerInterface interface
- Increased test coverage
- Added all the required PHP extensions to the Composer's `require` section so that
  now Composer will check dependencies on library installation time
- Added support of default Worker's `perform` method
- Added `Qless\Events\QlessCoreEvent` DTO and `Qless\Events\QlessCoreEventFactory` to interact with qless-core events
- Added `Qless\Client::getWorkerName` to provide default worker name
- Added ability to select the redis database
- Introduced job reservers (ordered, round robin, shuffled round robin)
- Introduced `Qless\EventsManger` to provide a basic event system

### Changed
- PHP 5.x is no longer supported. Minimal required version is 7.1
- Updated qless-core
- Move `Qless\Lua` to the `Qless\LuaScript`
- Move `Qless\Listener` to the `Qless\Subscribers\QlessCoreSubscriber`
- Move `Qless\Job` to the `Qless\Jobs\Job`
- Move `Qless\Jobs` to the `Qless\Jobs\Collection`
- Move `Qless\Worker` to the `Qless\Workers\ForkingWorker`
- More code quality improvements to follow the SRP. Thus the code base for almost all classes has been changed
- Move all the exceptions to the common namespace and implement the same `Qless\Exceptions\ExceptionInterface`
- Changed `Qless\Queue::put` signature from the `put($className, $jid, $data, ...)`
  to the `put(string className, array $data, ?string $jid = null, ...)`
- Now `Qless\Queue::pop` does not require the mandatory presence of the worker name as its 1st argument.
  If the the worker name is not passed the `Qless\Client::getWorkerName` will be used
- Now calling `Qless\Queue::pop` without 2nd argument (number of jobs to pop off of the queue) will return
  `Qless\Job|null` so that there is no need to play with arrays like `$job[0]->function()`
- Rework Job class to remove no longer needed getters
- Changed signature of the `Qless\Job::requeue` from `requeue(array $opts = []): string` to
  `requeue(?string $queue = null, array $opts = []): string` so that there is an ability to move job to a new queue

### Removed
- Fully refactor the `Qless\Client` class and removed no longer used code
- Removed no longer required `Qless\Qless::fork` and `pcntl_fork` check
- Removed no longer needed `Qless\Qless` class
- Removed no longer needed `Qless\Job::fromJobData`
- Removed no longer supported by qless-core `Qless\Resource`, `Qless\Job::getResources` and `Qless\Job::getInterval`
- The `Qless\Client::paused` no longer provided by qless-core (but we're saved `Qless\Queue::isPaused`)

### Fixed
- Fixed demo

## 1.0.0 - 2018-08-30
### Added
 - Initial stable release

[Unreleased]: https://github.com/pdffiller/qless-php/compare/v1.0.0...HEAD
