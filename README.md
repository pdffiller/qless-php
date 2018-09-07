# qless-php

[![Build Status](https://travis-ci.org/pdffiller/qless-php.svg?branch=master)](https://travis-ci.org/pdffiller/qless-php)
[![Code Coverage](https://codecov.io/gh/pdffiller/qless-php/branch/master/graph/badge.svg)](https://codecov.io/gh/pdffiller/qless-php)

PHP Bindings for qless.


Qless is a powerful Redis-based job queueing system inspired by [resque](https://github.com/chrisboulton/php-resque),
but built on a collection of Lua scripts, maintained in the [qless-core repo](https://github.com/seomoz/qless-core).
Be sure to check the [change log](https://github.com/pdffiller/qless-php/blob/master/CHANGELOG.md).

**NOTE:** This library is fully reworked and separately developed version of
[Contatta's qless-php](https://github.com/Contatta/qless-php). The copyright to the
[Contatta/qless-php](https://github.com/Contatta/qless-php) code belongs to [Ryver, Inc](https://ryver.com).
For more see the [Contatta/qless-php license](https://github.com/Contatta/qless-php/commit/fab97f490157581d6171b165ab9a0a9e83b69005).

Documentation is borrowed from [seomoz/qless](https://github.com/seomoz/qless).

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

1. **Jobs don't get dropped on the floor** -- Sometimes workers drop jobs. Qless
  automatically picks them back up and gives them to another worker
1. **Tagging / Tracking** -- Some jobs are more interesting than others. Track those
  jobs to get updates on their progress. Tag jobs with meaningful identifiers to
  find them quickly in the UI.
1. **Job Dependencies** -- One job might need to wait for another job to complete
1. **Stats** -- `qless` automatically keeps statistics about how long jobs wait
  to be processed and how long they take to be processed. Currently, we keep
  track of the count, mean, standard deviation, and a histogram of these times.
1. **Job data is stored temporarily** -- Job info sticks around for a configurable
  amount of time so you can still look back on a job's history, data, etc.
1. **Priority** -- Jobs with the same priority get popped in the order they were
  inserted; a higher priority means that it gets popped faster
1. **Retry logic** -- Every job has a number of retries associated with it, which are
  renewed when it is put into a new queue or completed. If a job is repeatedly
  dropped, then it is presumed to be problematic, and is automatically failed.
1. **Web App** -- With the advent of a Ruby client, there is a Sinatra-based web
  app that gives you control over certain operational issues
1. **Scheduled Work** -- Until a job waits for a specified delay (defaults to 0),
  jobs cannot be popped by workers
1. **Recurring Jobs** -- Scheduling's all well and good, but we also support
  jobs that need to recur periodically.
1. **Notifications** -- Tracked jobs emit events on pubsub channels as they get
  completed, failed, put, popped, etc. Use these events to get notified of
  progress on jobs you're interested in.

## Contributing and Developing

Please see [CONTRIBUTING.md](https://github.com/pdffiller/qless-php/blob/master/CONTRIBUTING.md).

## Usage

### Enqueing Jobs

**`@todo`**

### Running A Worker

**`@todo`**

### Web Interface

**`@todo`**

### Job Dependencies

**`@todo`**

### Priority

**`@todo`**

### Scheduled Jobs

**`@todo`**

### Recurring Jobs

**`@todo`**

### Configuration Options

**`@todo`**

### Tagging / Tracking

**`@todo`**

### Notifications

**`@todo`**

### Heartbeating

**`@todo`**

### Stats

**`@todo`**

### Time

**`@todo`**

### Ensuring Job Uniqueness

**`@todo`**

### Setting Default Job Options

**`@todo`**

### Testing Jobs

**`@todo`**

### Demo

See the [`./demo/`](https://github.com/pdffiller/qless-php/tree/master/demo) directory contents for a simple example.

## License

qless-php is open-sourced software licensed under the MIT License.
See the [`LICENSE.txt`](https://github.com/pdffiller/qless-php/blob/master/LICENSE.txt) file for more.


© 2018 PDFfiller<br>
© 2013-2015 Ryver, Inc <br>

All rights reserved.
