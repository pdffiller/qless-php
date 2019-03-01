# qless-php

[![Build Status](https://travis-ci.org/pdffiller/qless-php.svg?branch=master)](https://travis-ci.org/pdffiller/qless-php)
[![Code Coverage](https://codecov.io/gh/pdffiller/qless-php/branch/master/graph/badge.svg)](https://codecov.io/gh/pdffiller/qless-php)
[![Infection MSI](https://badge.stryker-mutator.io/github.com/pdffiller/qless-php/master)](https://infection.github.io)

PHP Bindings for qless.

Qless is a powerful Redis-based job queueing system inspired by [resque](https://github.com/chrisboulton/php-resque),
but built on a collection of Lua scripts, maintained in the [qless-core repo](https://github.com/seomoz/qless-core).
Be sure to check the [change log](https://github.com/pdffiller/qless-php/blob/master/CHANGELOG.md).

A big thank you to our [contributors](https://github.com/pdffiller/qless-php/graphs/contributors); you rock!

**NOTE:** This library is fully reworked and separately developed version of
[Contatta's qless-php](https://github.com/Contatta/qless-php). The copyright to the
[Contatta/qless-php](https://github.com/Contatta/qless-php) code belongs to [Ryver, Inc](https://ryver.com).
For more see the [Contatta/qless-php license](https://github.com/Contatta/qless-php/commit/fab97f490157581d6171b165ab9a0a9e83b69005).

Documentation is borrowed from [seomoz/qless](https://github.com/seomoz/qless).

## Contents

- [Philosophy and Nomenclature](#philosophy-and-nomenclature)
- [Features](#features)
- [Installation](#installation)
  - [Requirements](#requirements)
- [Usage](#usage)
  - [Enqueing Jobs](#enqueing-jobs)
  - [Running A Worker](#running-a-worker)
    - [Custom Job Handler](#custom-job-handler)
  - [Web Interface](#web-interface)
  - [Job Dependencies](#job-dependencies)
  - [Priority](#priority)
  - [Scheduled Jobs](#scheduled-jobs)
  - [Recurring Jobs](#recurring-jobs)
  - [Topics](#topics)
  - [Configuration Options](#configuration-options)
  - [Tagging / Tracking](#tagging--tracking)
  - [Event System](#event-system)
    - [Per-Job Events](#per-job-events)
    - [List of Events](#list-of-events)
  - [Sync job processing](#sync-job-processing)
  - [Heartbeating](#heartbeating)
  - [Stats](#stats)
  - [Time](#time)
  - [Ensuring Job Uniqueness](#ensuring-job-uniqueness)
  - [Setting Default Job Options](#setting-default-job-options)
  - [Testing Jobs](#testing-jobs)
- [Contributing and Developing](#contributing-and-developing)
- [License](#license)

## Philosophy and Nomenclature

A `job` is a unit of work identified by a job id or `jid`. A `queue` can contain several jobs that are scheduled to be
run at a certain time, several jobs that are waiting to run, and jobs that are currently running. A `worker` is a process
on a host, identified uniquely, that asks for jobs from the queue, performs some process associated with that job, and
then marks it as complete. When it's completed, it can be put into another queue.

Jobs can only be in one queue at a time. That queue is whatever queue they were last put in. So if a worker is working
on a job, and you move it, the worker's request to complete the job will be ignored.

A job can be `canceled`, which means it disappears into the ether, and we'll never pay it any mind ever again. A job can
be `dropped`, which is when a worker fails to heartbeat or complete the job in a timely fashion, or a job can be
`failed`, which is when a host recognizes some systematically problematic state about the job. A worker should only fail
a job if the error is likely not a transient one; otherwise, that worker should just drop it and let the system reclaim it.

## Features

- **Jobs don't get dropped on the floor** — Sometimes workers drop jobs. Qless automatically picks them back up and
  gives them to another worker.
- **Tagging / Tracking** — Some jobs are more interesting than others. Track those jobs to get updates on their
  progress. Tag jobs with meaningful identifiers to find them quickly in [the UI](#web-interface).
- **Topics** — Deliver a job to multiple queues.
- **Job Dependencies** — One job might need to wait for another job to complete,
- **Stats** — `qless` automatically keeps statistics about how long jobs wait to be processed and how long they take to
  be processed. Currently, we keep track of the count, mean, standard deviation, and a histogram of these times.
- **Job data is stored temporarily** — Job info sticks around for a configurable amount of time so you can still look
  back on a job's history, data, etc.
- **Priority** — Jobs with the same priority get popped in the order they were inserted; a higher priority means that
  it gets popped faster.
- **Retry logic** — Every job has a number of retries associated with it, which are renewed when it is put into a new
  queue or completed. If a job is repeatedly dropped, then it is presumed to be problematic, and is automatically failed.
- **Web App** — With the advent of a Ruby client, there is a Sinatra-based web app that gives you control over certain
  operational issues.
- **Scheduled Work** — Until a job waits for a specified delay (defaults to 0), jobs cannot be popped by workers.
- **Recurring Jobs** — Scheduling's all well and good, but we also support jobs that need to recur periodically.
- **Notifications** — Tracked jobs emit events on [pubsub](https://en.wikipedia.org/wiki/Publish%E2%80%93subscribe_pattern)
  channels as they get completed, failed, put, popped, etc. Use these events to get notified of
  progress on jobs you're interested in.

## Installation

### Requirements

Prerequisite PHP extensions are:

- [`json`](http://php.net/manual/en/book.json.php)
- [`pcntl`](http://php.net/manual/en/book.pcntl.php)
- [`posix`](http://php.net/manual/en/book.posix.php)
- [`pcre`](http://php.net/manual/en/book.pcre.php)
- [`sockets`](http://php.net/manual/en/book.sockets.php)

Supported PHP versions are: **7.1**, **7.2** and **7.3**.

Qless PHP can be installed via Composer:

```bash
composer require pdffiller/qless-php
```

Alternatively, install qless-php from source by checking it out from GitHub:

```bash
git clone git://github.com/pdffiller/qless-php.git
cd qless-php
composer update
```

NOTE: The `master` branch will always contain the latest _unstable_ version.
If you wish to check older versions or formal, tagged release, please switch to the relevant
[release](https://github.com/pdffiller/qless-php/releases).


The `adm` directory contains the configuration examples useful for system administrators.

## Usage

### Enqueing Jobs

First things first, create a Qless Client. The Client accepts all the same arguments that you'd use when constructing
a [Predis\Client](https://github.com/nrk/predis#connecting-to-redis) client.

```php
use Qless\Client;

// Connect to localhost
$client = new Client();

// Connect to somewhere else
$client = new Client('127.0.0.99:1234');
```

Jobs should be classes that define a `perform` method, which must accept a single `Qless\Jobs\BaseJob` argument:

```php
use Qless\Jobs\BaseJob;

class MyJobClass
{
    /**
     * @param BaseJob $job Is an instance of `Qless\Jobs\BaseJob` and provides access
     *                     to the payload data via `$job->getData()`, a means to cancel
     *                     the job (`$job->cancel()`), and more.
     */
    public function perform(BaseJob $job): void
    {
        // ...
        echo 'Perform ', $job->getId(), ' job', PHP_EOL;
        
        $job->complete();
    }
}
```

Now you can access a queue, and add a job to that queue.

```php
/**
 * This references a new or existing queue 'testing'.
 * @var \Qless\Queues\Queue $queue
 */
$queue = $client->queues['testing'];

// Let's add a job, with some data. Returns Job ID
$jid = $queue->put(MyJobClass::class, ['hello' => 'howdy']);
// $jid here is "696c752a706049cdb227a9fcfe9f681b"

/**
 * Now we can ask for a job.
 * @var \Qless\Jobs\BaseJob $job
 */
$job = $queue->pop();

// And we can do the work associated with it!
$job->perform();
// Perform 316eb06a30d24d66ad0d33361306a7a1 job
```

The job data must be serializable to JSON, and it is recommended that you use a hash for it.
See below for a list of the supported job options.


The argument returned by `queue->put()` is the `jid` (Job ID).
Every Qless job has a unique `jid`, and it provides a means to interact with an existing job:

```php
// find an existing job by it's JID
$job = $client->jobs[$jid];

// query it to find out details about it:
$job->jid;          // the job id
$job->klass;        // the class of the job
$job->queue;        // the queue the job is in
$job->data;         // the data for the job
$job->history;      // the history of what has happened to the job so far
$job->dependencies; // the jids of other jobs that must complete before this one
$job->dependents;   // the jids of other jobs that depend on this one
$job->priority;     // the priority of this job
$job->worker;       // the internal worker name (usually consumer identifier)
$job->tags;         // array of tags for this job
$job->expires;      // when you must either check in with a heartbeat or turn it in as completed
$job->remaining;    // the number of retries remaining for this job
$job->retries;      // the number of retries originally requested
$job->tracked;      // is job flagged as important
$job->failed;       // is job flagged as failed

// there is a way to get seconds remaining before this job will timeout:
$job->ttl();

// you can also change the job in various ways:
$job->requeue('some_other_queue'); // move it to a new queue
$job->cancel();                    // cancel the job
$job->tag('foo');                  // add a tag
$job->untag('foo');                // remove a tag
$job->track();                     // start tracking current job
$job->untrack();                   // stop tracking current job
```

### Running A Worker

The Qless PHP worker was heavily inspired by [Resque](https://github.com/chrisboulton/php-resque)'s worker, but thanks
to the power of the qless-core lua scripts, it is much simpler and you are welcome to write your own (e.g. if you'd
rather save memory by not forking the worker for each job).

As with resque...

- The worker forks a child process for each job in order to provide resilience against memory leaks
  (Pass the `RUN_AS_SINGLE_PROCESS` environment variable to force Qless to not fork the child process.
  Single process mode should only be used in some test/dev environments.)
- The worker updates its procline with its status so you can see what workers are doing using `ps`
- The worker registers signal handlers so that you can control it by sending it signals
- The worker is given a list of queues to pop jobs off of
- The worker logs out put based on setting of the `Psr\Log\LoggerInterface` instance passed to worker

Resque uses queues for its notion of priority. In contrast, qless has priority support built-in.
Thus, the worker supports two strategies for what order to pop jobs off the queues: ordered and round-robin.
The ordered reserver will keep popping jobs off the first queue until it is empty, before trying to pop job off the
second queue. The [round-robin](https://en.wikipedia.org/wiki/Round-robin_scheduling) reserver will pop a job off
the first queue, then the second queue, and so on. You could also easily implement your own.

To start a worker, write a bit of PHP code that instantiates a worker and runs it.
You could write a simple script to do this, for example:

```php
// The autoloader line is omitted

use Qless\Client;
use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Workers\ForkingWorker;

// Create a client
$client = new Client();

// Get the queues you use.
//
// Create a job reserver; different reservers use different
// strategies for which order jobs are popped off of queues
$reserver = new OrderedReserver($client->queues, ['testing', 'testing-2', 'testing-3']);

$worker = new ForkingWorker($reserver, $client);
$worker->run();
```

There are different job reservers.

* `DefaultReserver`: A default job reserver
* `OrderedReserver`: Orders queues by its name
* `PriorityReserver`: Orders queues by its priority
* `RoundRobinReserver`: Round-robins through all the provided queues
* `ShuffledRoundRobin`: Like RoundRobinReserver but shuffles the order of the queues

The following POSIX-compliant signals are supported in the parent process:

- `TERM`: Shutdown immediately, stop processing jobs
- `INT`:  Shutdown immediately, stop processing jobs
- `QUIT`: Shutdown after the current job has finished processing
- `USR1`: Kill the forked child immediately, continue processing jobs
- `USR2`: Don't process any new jobs, and dump the current backtrace
- `CONT`: Start processing jobs again after a `USR2`

_For detailed info regarding the signals refer to [`signal(7)`](http://man7.org/linux/man-pages/man7/signal.7.html)._

You should send these to the master process, not the child.

The child process supports the `USR2` signal, which causes it to dump its current backtrace.

#### Custom Job Handler

There is an ability to set custom Job Handler to process jobs. To do this call 
`\Qless\Workers\WorkerInterface::registerJobPerformHandler` method. Its argument should implement the
`\Qless\Jobs\PerformAwareInterface` interface. This approach is handy when Job Handler is complicated service and/or
has dependencies. Let's look at an example in which we need to get a custom Job Handler created by external factory: 

```php
use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Workers\ForkingWorker;

/** 
 *  @var \Qless\Client $client 
 *  @var object $jobHandlerFactory is some complicated factory which knows how to create Job Handler   
 */
$jobHandler = $jobHandlerFactory->createJobHandler();

$reserver = new OrderedReserver($client->queues, 'my-queue');

$worker = new ForkingWorker($reserver, $client);
$worker->registerJobPerformHandler($jobHandler);

$worker->run();
```

### Web Interface

The Qless PHP does not ships with a web app. However there is a resque-inspired web app provided by
[seomoz/qless](https://github.com/seomoz/qless#web-interface). In addition, you can take advantage of
[docker based](https://github.com/seomoz/qless-docker) dashboard. We're plan to create a robust and elegant web
interface using PHP framework, but that task does not have the highest priority.

### Job Dependencies

Let's say you have one job that depends on another, but the task definitions are fundamentally different.
You need to bake a turkey, and you need to make stuffing, but you can't make the turkey until the stuffing is made:

```php
/** @var \Qless\Queues\Queue $queue */
$queue = $client->queues['cook'];

$jid = $queue->put(MakeStuffing::class, ['lots' => 'of butter']);

$queue->put(
    MakeTurkey::class,      // The class with the job perform method.
    ['with' => 'stuffing'], // An array of parameters for job.
    null,                   // The specified job id, if not a specified, a jid will be generated.
    null,                   // The specified delay to run job.
    null,                   // Number of retries allowed.
    null,                   // A greater priority will execute before jobs of lower priority.
    null,                   // An array of tags to add to the job.
    [$jid]                  // A list of JIDs this job must wait on before executing.
);
```

When the stuffing job completes, the turkey job is unlocked and free to be processed.

### Priority

Some jobs need to get popped sooner than others. Whether it's a trouble ticket, or debugging, you can do this pretty
easily when you put a job in a queue:

```php
/** @var \Qless\Queues\Queue $queue */
$queue->put(MyJobClass::class, ['foo' => 'bar'], null, null, null, 10);
```

What happens when you want to adjust a job's priority while it's still waiting in a queue?

```php
/** @var \Qless\Client $client */
$job = $client->jobs['0c53b0404c56012f69fa482a1427ab7d'];

// Now this will get popped before any job of lower priority.
$job->priority = 10;
```

### Scheduled Jobs

If you don't want a job to be run right away but some time in the future, you can specify a delay:

```php
/**
 * Run at least 10 minutes from now.
 *
 * @var \Qless\Queues\Queue $queue
 */
$queue->put(MyJobClass::class, ['foo' => 'bar'], null, 600);
```

This doesn't guarantee that job will be run exactly at 10 minutes. You can accomplish this by changing the job's
priority so that once 10 minutes has elapsed, it's put before lesser-priority jobs:

```php
/**
 * Run in 10 minutes.
 *
 * @var \Qless\Queues\Queue $queue
 */
$queue->put(MyJobClass::class, ['foo' => 'bar'], null, 600, null, 100);
```

### Recurring Jobs

Sometimes it's not enough simply to schedule one job, but you want to run jobs regularly.
In particular, maybe you have some batch operation that needs to get run once an hour and you don't care what
worker runs it. Recurring jobs are specified much like other jobs:

```php
/**
 * Run every hour.
 *
 * @var \Qless\Queues\Queue $queue
 */
$jid = $queue->recur(MyJobClass::class, ['widget' => 'warble'], 3600);
// $jid here is "696c752a706049cdb227a9fcfe9f681b"
```

You can even access them in much the same way as you would normal jobs:

```php
/**
 * @var \Qless\Client $client
 * @var \Qless\Jobs\RecurringJob $job
 */
$job = $client->jobs['696c752a706049cdb227a9fcfe9f681b'];
```

Changing the interval at which it runs after the fact is trivial:

```php
/**
 * I think I only need it to run once every two hours.
 *
 * @var \Qless\Jobs\RecurringJob $job
 */
$job->interval = 7200;
```

If you want it to run every hour on the hour, but it's 2:37 right now, you can specify an offset which is how long
it should wait before popping the first job:

```php
/**
 * 23 minutes of waiting until it should go.
 *
 * @var \Qless\Queues\Queue $queue
 */
$queue->recur(MyJobClass::class, ['howdy' => 'hello'], 3600, 23 * 60);
```

Recurring jobs also have priority, a configurable number of retries, and tags. These settings don't apply to the
recurring jobs, but rather the jobs that they create. In the case where more than one interval passes before a worker
tries to pop the job, **more than one job is created**. The thinking is that while it's completely client-managed,
the state should not be dependent on how often workers are trying to pop jobs.

```php
/**
 * Recur every minute.
 *
 * @var \Qless\Queues\Queue $queue
 */
$queue->recur(MyJobClass::class, ['lots' => 'of jobs'], 60);

// Wait 5 minutes
$jobs = $queue->pop(null, 10);
echo count($jobs), ' jobs got popped'; // 5 jobs got popped
```

### Topics
Topic help you to put job to different queues. 
First, you must to create subscription. You can use pattern for name of topics. 
Symbol `*` - one word, `#` - few words divided by point `.`. 
Examples: `first.second.*`, `*.second.*`, `#.third`.

```php
/**
 * Subscribe
 *
 * @var \Qless\Queues\Queue $queue
 */
$queue1->subscribe('*.*.apples');
$queue2->subscribe('big.*.apples');
$queue3->subscribe('#.apples');

```

Than you can put job to all subscribers.

```php
/**
 * Put to few queues
 *
 * @var \Qless\Topics\Topic
 */
$topic = new Topic('big.green.apples', $client);
$topic->put('ClassName', ['key' => 'value']); // Put to $queue1, $queue2 and $queue3

```

You can call all Queue's public methods for Topic.

### Configuration Options

You can get and set global (read: in the context of the same Redis instance) configuration to change the behavior for
heartbeating, and so forth. There aren't a tremendous number of configuration options, but an important one is how
long job data is kept around. Job data is expired after it has been completed for `jobs-history` seconds, but is limited
to the last `jobs-history-count` completed jobs. These default to 50k jobs, and 30 days, but depending on volume,
your needs may change. To only keep the last 500 jobs for up to 7 days:

```php
/** @var \Qless\Client $client */
$client->config['jobs-history'] = 7 * 86400;
$client->config['jobs-history-count'] = 500;
```

### Tagging / Tracking

In qless, 'tracking' means flagging a job as important. Tracked jobs have a tab reserved for them in the web interface,
and they also emit subscribable events as they make progress (more on that below). You can flag a job from the
[web interface](#web-interface), or the corresponding code:

```php
/** @var \Qless\Client $client */
$client->jobs['b1882e009a3d11e192d0b174d751779d']->track();
```

Jobs can be tagged with strings which are indexed for quick searches. For example, jobs might be associated with
customer accounts, or some other key that makes sense for your project.

```php
/**  @var \Qless\Queues\Queue $queue */
$queue->put(MyJobClass::class, ['tags' => 'aplenty'], null, null, null, null, ['12345', 'foo', 'bar']);
```

This makes them searchable in the web interface, or from code:

```php
/** @var \Qless\Client $client */
$jids = $client->jobs->tagged('foo');
```

You can add or remove tags at will, too:

```php
/**
 * @var \Qless\Client $client
 * @var \Qless\Jobs\BaseJob $job
 */
$job = $client->jobs['b1882e009a3d11e192d0b174d751779d'];
$job->tag('howdy', 'hello');
$job->untag('foo', 'bar');
```

#### Event System

Qless also has a basic event system that can be used by your application to customize how some of the qless internals
behave. Events can be used to inject logic before, after or around the processing of a single job in the child process.
This can be useful, for example, when you need to re-establish a connection to your database for each job.

Events has few main concepts - `entity`, `happening`, `source`.
- `entity` is an object type (component) with whom event is taking place, for example `entity` can be `job`, `worker`, `queue`.
- `happening` is an act that is taking place, for example it can be `beforeFork` or `beforePerform`
- `source` is an object who fired an event
 
In code Event is represented by some class. All events classes are descendants of `\Qless\Events\User\AbstractEvent` class.
You can get `entity` and `happening` of event by calling static methods `getEntityName()` and `getHappening()`,
you can get full name of event (made of `entity` and `happening`) by calling static method `getName()` 
`source` you can get with `getSource()`.

Also, there are subscribers for events. Subscriber can be any class with methods named as event's `happening` (example: `beforeFork(AbstractEvent $event)`).
Also subscriber can be a closure. Handling method of subscriber will receive only one parameter - event, you can get all data you need from that event. 

You can attach subscriber to a specific event or events group (grouped bye events `entity`) 

Example: Define a subscriber with an `beforeFork` method that will be called where you want the job to be processed:

```php
use Acme\Database\Connection;
use Qless\Events\User\AbstractEvent;
use Qless\Workers\ForkingWorker;

class ReEstablishDBConnection
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function beforeFork(AbstractEvent $event): void
    {
        $this->connection->connect();
    }
}
```

Then, attach subscriber to the `worker` events group:

```php
use Qless\Events\User\Worker\AbstractWorkerEvent;

/** @var \Qless\Workers\ForkingWorker $worker */
$worker->getEventsManager()->attach(AbstractWorkerEvent::getEntityName(), new ReEstablishDBConnection());
```

To attach subscriber to a specific event you can do:

```php
use \Qless\Events\User\Worker\BeforeFork;

/** @var \Qless\Workers\ForkingWorker $worker */
$worker->getEventsManager()->attach(BeforeFork::getName(), new ReEstablishDBConnection());
```

You can attach subscribers as many as you want. Qless events system supports priories so you can change default priority:

```php
use Qless\Events\User\Worker\AbstractWorkerEvent;

/** @var \Qless\Workers\ForkingWorker $worker */
$worker->getEventsManager()->attach(AbstractWorkerEvent::getEntityName(), new MySubscriber1(), 150); // More priority
$worker->getEventsManager()->attach(AbstractWorkerEvent::getEntityName(), new MySubscriber2(), 100); // Normal priority
$worker->getEventsManager()->attach(AbstractWorkerEvent::getEntityName(), new MySubscriber10(), 50); // Less priority
```

#### Per-Job Events

As it was mentioned above, Qless supports events on a per-entity basis. 
So per-job events are available if you have some orthogonal logic to run in the context of some (but not all) jobs. 
Every Job Event class is an descendant of `\Qless\Events\User\Job\AbstractJobEvent` and contains entity of `\Qless\Jobs\BaseJob`.
To get the job from event you can use `$event->getJob()` method.

Per-job subscribes can be defined the same way as worker's subscribers:

```php
use Qless\Events\User\Job\BeforePerform;
use Qless\Jobs\BaseJob;
use Qless\Jobs\PerformAwareInterface;
use My\Database\Connection;

class ReEstablishDBConnection
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param BeforePerform $event
     * @param BaseJob|PerformAwareInterface $source
     */
    public function beforePerform(BeforePerform $event, $source): void
    {
        $this->connection->connect();
    }
}
```

To add them to a job class, you first have to make your job class events-aware by subscribing on the required events
group. To achieve this just implement `setUp` method and subscribe to the desired events:

```php
use Qless\Events\User\Job\AbstractJobEvent;
use Qless\Jobs\BaseJob;
use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;

class EventsDrivenJobHandler implements EventsManagerAwareInterface
{
    use EventsManagerAwareTrait;

    public function setUp()
    {
        $this->getEventsManager()->attach(AbstractJobEvent::getEntityName(), new ReEstablishDBConnection());
    }

    public function perform(BaseJob $job): void
    {
        // ...

        $job->complete();
    }
}
```

**Note**: In this scenario your job class must implement `Qless\EventsManagerAwareInterface`.

Yet another example. Let's assume that job's payload should always contain additional data from the current context.
You can easily amend it using the `BeforeEnqueue` subscriber:

```php
use Qless\Events\User\Queue\BeforeEnqueue;

/** @var \Qless\Client $client */
$client
    ->getEventsManager()
    ->attach('queue:beforeEnqueue', function (BeforeEnqueue $event) {
        $event->getData()['metadata'] = [
            'server_address' => $_SERVER['SERVER_ADDR'],
        ];
    });
```

#### List of Events
                             
Full list of events available in Qless:

| Entity      | Event                    | Class
| ----------- | ------------------------ | ------------------------
| **Job**     | `job:beforePerform`      | `\Qless\Events\User\Job\BeforePerform`
| **Job**     | `job:afterPerform`       | `\Qless\Events\User\Job\AfterPerform`
| **Job**     | `job:onFailure`          | `\Qless\Events\User\Job\OnFailure`
| **Worker**  | `worker:beforeFirstWork` | `\Qless\Events\User\Worker\BeforeFirstWork`
| **Worker**  | `worker:beforeFork`      | `\Qless\Events\User\Worker\BeforeFork`
| **Worker**  | `worker:afterFork`       | `\Qless\Events\User\Worker\AfterFork`
| **Queue**   | `queue:beforeEnqueue`    | `\Qless\Events\User\Queue\BeforeEnqueue`
| **Queue**   | `queue:afterEnqueue`     | `\Qless\Events\User\Queue\AfterEnqueue`

### Sync job processing

If you want your job to be processed without worker, you can set sync mode for qless client. In configuration of your project write code like this:
```php
/** @var \Qless\Client $client */
$client->config->set('sync-enabled', true);
``` 
Now you all job will be process without worker, synchronously.

**Note**: Use it feature for testing your job in development environment.

### Heartbeating

When a worker is given a job, it is given an exclusive lock to that job. That means
that job won't be given to any other worker, so long as the worker checks in with
progress on the job. By default, jobs have to either report back progress every 60
seconds, or complete it, but that's a configurable option. For longer jobs, this
may not make sense.

``` php
$job = $queue->pop();
// How long until I have to check in?
$job->ttl(); // 59

    // ...

    public function perform(BaseJob $job): void
    {
        // some code
        
        // if job need more time
        $job->heartbeat();
        
        // some code
        
        $job->complete();
    }
    
    // ...    

```

If you want to set the heartbeat in all queues,

``` php
// Set 10 minutes
$client->config->set('heartbeat', 600);
```

Also, you can set heartbeat for a separate queue

``` php
$client->queues['test-queue']->heartbeat = 120;
```

### Stats

One nice feature of `qless` is that you can get statistics about usage. Stats are aggregated by day, 
so when you want stats about a queue, you need to say what queue and what day you're talking about. 
By default, you just get the stats for today. These stats include information about the mean job wait time, 
standard deviation, and histogram. This same data is also provided for job completion:

``` php
// Today stat
$client->stats('queue_name', time());
// {"run":{"std":0.027175949738075,"histogram":[...],"mean":0.011884652651273,"count":26},"failures":0,"retries":0,"failed":0,"wait":{"std":56188.180755369,"histogram":[...],"mean":32870.757469205,"count":26}}
```

### Time

Redis doesn't allow access to the system time if you're going to be making any manipulations to data. 
But Qless have heartbeating. When the client making most requests, it actually send the current time. 
So, all workers must be synchronized.

### Ensuring Job Uniqueness

Qless generate Job Id automatically, but you can set it manually.

``` php
// automatically
$queue->put($className, $data);

// manually
$queue->put($className, $data, 'abcdef123456');
```

For example, Job Id can be based on className and payload. It'll guaranteed that Qless won't have 
multiple jobs with the same class and data.
Also, it helps for debugging on dev environment. 

### Setting Default Job Options

* jid
* delay
* priority
* tags
* retries
* depends

All of this options have default value. Also, you can define default job 
options directly on the job class:

``` php
$queue->put(
    Job::class,             // Class - require 
    ['key1' => 'value1'],   // Payload - require 
    'custom-job-id',        // Manually id
    10,                     // Delay 10 seconds
    3,                      // Three retries
    7,                      // Priority
    ['important', 'media'], // Tags
    [$jidFirst, $jidSecond] // Depends jobs
);
```

### Testing Jobs

You can use [syncronize](#sync-job-processing) to handle jobs for testing.
In this regime, all jobs will be running immediately.

## Contributing and Developing

Please see [CONTRIBUTING.md](https://github.com/pdffiller/qless-php/blob/master/CONTRIBUTING.md).

## License

qless-php is open-sourced software licensed under the MIT License.
See the [`LICENSE.txt`](https://github.com/pdffiller/qless-php/blob/master/LICENSE.txt) file for more.


© 2018-2019 PDFfiller<br>
© 2013-2015 Ryver, Inc <br>

All rights reserved.
