if #KEYS > 0 then error('No Keys should be provided') end

-- StringUtils module start
local stringUtils = {}

function stringUtils.trim(s)
  return (s:gsub("^%s*(.-)%s*$", "%1"))
end

function stringUtils.isEmptyOrSpaces(s)
  local _trimmed = stringUtils.trim(s)
  return (_trimmed == '' or _trimmed == nil)
end
-- StringUtils module end

-- TableUtils module start
local tableUtils = {}

function tableUtils.extend(self, other)
  for i, v in ipairs(other) do
    table.insert(self, v)
  end
end

function tableUtils.filterEmptyItems (items)
  local results = {}
  for _,item in ipairs(items) do
    if not stringUtils.isEmptyOrSpaces(item) then table.insert(results, item) end
  end
  return results
end
-- TableUtils module end

local function errorMethodNotSupported(methodName)
  error(methodName .. '(): This method is not supported in light version of script')
end

local Qless = {
  ns = 'ql:'
}

local QlessQueue = {
  ns = Qless.ns .. 'q:'
}
QlessQueue.__index = QlessQueue

local QlessWorker = {
  ns = Qless.ns .. 'w:'
}
QlessWorker.__index = QlessWorker

local QlessJob = {
  ns = Qless.ns .. 'j:'
}
QlessJob.__index = QlessJob

local QlessRecurringJob = {}
QlessRecurringJob.__index = QlessRecurringJob

Qless.publish = function(channel, message)
  redis.call('publish', Qless.ns .. channel, message)
end

Qless.job = function(jid)
  assert(jid, 'Job(): no jid provided')
  local job = {}
  setmetatable(job, QlessJob)
  job.jid = jid
  return job
end

Qless.recurring = function(jid)
  assert(jid, 'Recurring(): no jid provided')
  local job = {}
  setmetatable(job, QlessRecurringJob)
  job.jid = jid
  return job
end

Qless.failed = function(group, start, limit)
  start = assert(tonumber(start or 0),
    'Failed(): Arg "start" is not a number: ' .. (start or 'nil'))
  limit = assert(tonumber(limit or 25),
    'Failed(): Arg "limit" is not a number: ' .. (limit or 'nil'))

  if group then
    return {
      total = redis.call('llen', 'ql:f:' .. group),
      jobs  = redis.call('lrange', 'ql:f:' .. group, start, start + limit - 1)
    }
  else
    local response = {}
    local groups = redis.call('smembers', 'ql:failures')
    for index, group in ipairs(groups) do
      response[group] = redis.call('llen', 'ql:f:' .. group)
    end
    return response
  end
end

Qless.jobs = function(now, state, ...)
  assert(state, 'Jobs(): Arg "state" missing')

  local offset = assert(tonumber(arg[2] or 0),
    'Jobs(): Arg "offset" not a number: ' .. tostring(arg[2]))
  local count  = assert(tonumber(arg[3] or 25),
    'Jobs(): Arg "count" not a number: ' .. tostring(arg[3]))

  if state == 'complete' then
    local name  = arg[1];

    if (name == '') then
      return redis.call('zrevrange', 'ql:completed', offset, offset + count - 1)
    end

    local queue = Qless.queue(name)
    return queue.completed.peek(offset, count)
  end

  local name  = assert(arg[1], 'Jobs(): Arg "queue" missing')
  local queue = Qless.queue(name)
  if state == 'running' then
    return queue.locks.peek(now, offset, count)
  elseif state == 'stalled' then
    return queue.locks.expired(now, offset, count)
  elseif state == 'scheduled' then
    queue:check_scheduled(now, queue.scheduled.length())
    return queue.scheduled.peek(now, offset, count)
  elseif state == 'depends' then
    return queue.depends.peek(now, offset, count)
  elseif state == 'recurring' then
    return queue.recurring.peek('+inf', offset, count)
  elseif state == 'waiting' then
    return queue.work.peek(offset, count)
  else
    error('Jobs(): Unknown type "' .. state .. '"')
  end
end

Qless.track = function(now, command, jid)
  if command ~= nil then
    assert(jid, 'Track(): Arg "jid" missing')
    assert(Qless.job(jid):exists(), 'Track(): Job does not exist')
    if string.lower(command) == 'track' then
      Qless.publish('track', jid)
      return redis.call('zadd', 'ql:tracked', now, jid)
    elseif string.lower(command) == 'untrack' then
      Qless.publish('untrack', jid)
      return redis.call('zrem', 'ql:tracked', jid)
    else
      error('Track(): Unknown action "' .. command .. '"')
    end
  else
    local response = {
      jobs = {},
      expired = {}
    }
    local jids = redis.call('zrange', 'ql:tracked', 0, -1)
    for index, jid in ipairs(jids) do
      local data = Qless.job(jid):data()
      if data then
        table.insert(response.jobs, data)
      else
        table.insert(response.expired, jid)
      end
    end
    return response
  end
end

Qless.subscription = function(now, queue, command, topic)
  assert(command,
    'Tag(): Arg "command" must be "add", "remove", "get", "all", "exists"')

  if command == 'get' then
    return redis.call('keys', 'ql:topics' .. queue)
  elseif command == 'all' then
    local _topics = redis.call('keys', 'ql:topics*')
    local _value
    local _topic
    local _return = {}
    for i,v in ipairs(_topics) do
      _value = redis.call('hvals', v)
      _topic = redis.call('hkeys', v)
      for index,topic_name in ipairs(_topic) do
        if (_return[topic_name] == nil) then
          _return[topic_name] = {}
        end
        _return[topic_name][i .. index] = _value[index]
      end
    end
    return _return
  elseif command == 'exists' then
    local exists = redis.call('hexists', 'ql:topics' .. queue, topic)
    return (exists == 1)
  elseif command == 'remove' then
    local deleted = redis.call('hdel', 'ql:topics' .. queue, topic)
    return (deleted == 1)
  elseif command == 'add' then
    local topics = redis.call('hgetall', 'ql:topics' .. queue)
    local _topics = {}
    if topics then
      local index = 0
      for i,v in ipairs(topics) do _topics[v] = true end

      if _topics[topic] == nil or _topics[topic] == false then
        redis.call('hset', 'ql:topics' .. queue, topic, queue)
      end
    else
      redis.call('hset', 'ql:topics' .. queue, topic, queue)
      table.insert(topics, topic)
    end

    return redis.call('hvals', 'ql:topics' .. queue)
  else
    error('Tag(): First argument must be "add", "remove", "get" or "exists"')
  end
end

Qless.tags = function(cursor, count)
  errorMethodNotSupported('Qless.tags')
end

Qless.tag = function(now, command, ...)
  errorMethodNotSupported('Qless.tag')
end

Qless.cancel = function(...)
  for _, jid in ipairs(arg) do
    local state, queue, failure, worker = unpack(redis.call(
      'hmget', QlessJob.ns .. jid, 'state', 'queue', 'failure', 'worker'))

    if state ~= 'complete' then
      local encoded = cjson.encode({
        jid    = jid,
        worker = worker,
        event  = 'canceled',
        queue  = queue
      })
      Qless.publish('log', encoded)

      if worker and (worker ~= '') then
        redis.call('zrem', 'ql:w:' .. worker .. ':jobs', jid)
        Qless.publish('w:' .. worker, encoded)
      end

      if queue then
        local queue = Qless.queue(queue)
        queue.work.remove(jid)
        queue.locks.remove(jid)
        queue.scheduled.remove(jid)
      end

      if state == 'failed' then
        failure = cjson.decode(failure)
        redis.call('lrem', 'ql:f:' .. failure.group, 0, jid)
        if redis.call('llen', 'ql:f:' .. failure.group) == 0 then
          redis.call('srem', 'ql:failures', failure.group)
        end
        local bin = failure.when - (failure.when % 86400)
        local failed = redis.call(
          'hget', 'ql:s:stats:' .. bin .. ':' .. queue, 'failed')
        redis.call('hset',
          'ql:s:stats:' .. bin .. ':' .. queue, 'failed', failed - 1)
      end

      if redis.call('zscore', 'ql:tracked', jid) ~= false then
        Qless.publish('canceled', jid)
      end

      redis.call('del', QlessJob.ns .. jid)
      redis.call('del', QlessJob.ns .. jid .. '-history')
    end
  end

  return arg
end

Qless.config = {}

Qless.config.defaults = {
  ['application']        = 'qless',
  ['heartbeat']          = 60,
  ['grace-period']       = 10,
  ['stats-history']      = 30,
  ['histogram-history']  = 7,
  ['jobs-history-count'] = 50000,
  ['jobs-history']       = 604800,
  ['jobs-failed-history'] = 604800
}

Qless.config.get = function(key, default)
  if key then
    return redis.call('hget', 'ql:config', key) or
      Qless.config.defaults[key] or default
  else
    local reply = redis.call('hgetall', 'ql:config')
    for i = 1, #reply, 2 do
      Qless.config.defaults[reply[i]] = reply[i + 1]
    end
    return Qless.config.defaults
  end
end

Qless.config.set = function(option, value)
  assert(option, 'config.set(): Arg "option" missing')
  assert(value , 'config.set(): Arg "value" missing')
  Qless.publish('log', cjson.encode({
    event  = 'config_set',
    option = option,
    value  = value
  }))

  redis.call('hset', 'ql:config', option, value)
end

Qless.config.unset = function(option)
  assert(option, 'config.unset(): Arg "option" missing')
  Qless.publish('log', cjson.encode({
    event  = 'config_unset',
    option = option
  }))

  redis.call('hdel', 'ql:config', option)
end

function QlessJob:data(...)
  local job = redis.call(
    'hmget', QlessJob.ns .. self.jid, 'jid', 'klass', 'state', 'queue',
    'worker', 'expires', 'retries', 'remaining', 'data', 'failure',
    'spawned_from_jid')

  if not job[1] then
    return nil
  end

  local data = {
    jid              = job[1],
    klass            = job[2],
    state            = job[3],
    queue            = job[4],
    worker           = job[5] or '',
    tracked          = redis.call(
      'zscore', 'ql:tracked', self.jid) ~= false,
    priority         = 0,
    expires          = tonumber(job[6]) or 0,
    retries          = tonumber(job[7]),
    remaining        = math.floor(tonumber(job[8])),
    data             = job[9],
    tags             = {},
    history          = self:history(),
    failure          = cjson.decode(job[10] or '{}'),
    spawned_from_jid = job[11],
    dependents       = {},
    dependencies     = {}
  }

  if #arg > 0 then
    local response = {}
    for index, key in ipairs(arg) do
      table.insert(response, data[key])
    end
    return response
  else
    return data
  end
end

function QlessJob:complete(now, worker, queue, raw_data, ...)
  assert(worker, 'Complete(): Arg "worker" missing')
  assert(queue , 'Complete(): Arg "queue" missing')
  local data = assert(cjson.decode(raw_data),
    'Complete(): Arg "data" missing or not JSON: ' .. tostring(raw_data))

  local options = {}
  for i = 1, #arg, 2 do options[arg[i]] = arg[i + 1] end

  local nextq   = options['next']
  local delay   = assert(tonumber(options['delay'] or 0))

  if options['delay'] and nextq == nil then
    error('Complete(): "delay" cannot be used without a "next".')
  end

  local bin = now - (now % 86400)

  local lastworker, state, retries, current_queue = unpack(
    redis.call('hmget', QlessJob.ns .. self.jid, 'worker', 'state', 'retries',
      'queue'))

  if lastworker == false then
    error('Complete(): Job ' .. self.jid .. ' does not exist')
  elseif (state ~= 'running') then
    error('Complete(): Job ' .. self.jid .. ' is not currently running: ' ..
      state)
  elseif lastworker ~= worker then
    error('Complete(): Job ' .. self.jid ..
      ' has been handed out to another worker: ' .. tostring(lastworker))
  elseif queue ~= current_queue then
    error('Complete(): Job ' .. self.jid .. ' running in another queue: ' ..
      tostring(current_queue))
  end

  self:history(now, 'done')

  if raw_data then
    redis.call('hset', QlessJob.ns .. self.jid, 'data', raw_data)
  end

  local queue_obj = Qless.queue(queue)
  queue_obj.work.remove(self.jid)
  queue_obj.locks.remove(self.jid)
  queue_obj.scheduled.remove(self.jid)
  queue_obj.completed.add(now, self.jid)

  local time = tonumber(
    redis.call('hget', QlessJob.ns .. self.jid, 'time') or now)
  local waiting = now - time
  Qless.queue(queue):stat(now, 'run', waiting)
  redis.call('hset', QlessJob.ns .. self.jid,
    'time', string.format("%.20f", now))

  redis.call('zrem', 'ql:w:' .. worker .. ':jobs', self.jid)

  if redis.call('zscore', 'ql:tracked', self.jid) ~= false then
    Qless.publish('completed', self.jid)
  end

  if nextq then
    queue_obj = Qless.queue(nextq)
    Qless.publish('log', cjson.encode({
      jid   = self.jid,
      event = 'advanced',
      queue = queue,
      to    = nextq
    }))

    self:history(now, 'put', {q = nextq})

    if redis.call('zscore', 'ql:queues', nextq) == false then
      redis.call('zadd', 'ql:queues', now, nextq)
    end

    redis.call('hmset', QlessJob.ns .. self.jid,
      'state', 'waiting',
      'worker', '',
      'failure', '{}',
      'queue', nextq,
      'expires', 0,
      'remaining', tonumber(retries))

    if (delay > 0) then
      queue_obj.scheduled.add(now + delay, self.jid)

      return 'scheduled'
    else
      queue_obj.work.add(now, self.jid)

      return 'waiting'
    end
  else
    Qless.publish('log', cjson.encode({
      jid   = self.jid,
      event = 'completed',
      queue = queue
    }))

    redis.call('hmset', QlessJob.ns .. self.jid,
      'state', 'complete',
      'worker', '',
      'failure', '{}',
      'queue', '',
      'expires', 0,
      'remaining', tonumber(retries))

    local count = Qless.config.get('jobs-history-count')
    local time  = Qless.config.get('jobs-history')

    count = tonumber(count or 50000)
    time  = tonumber(time  or 7 * 24 * 60 * 60)

    redis.call('zadd', 'ql:completed', now, self.jid)

    local jids = redis.call('zrangebyscore', 'ql:completed', 0, now - time)
    for index, jid in ipairs(jids) do
      redis.call('del', QlessJob.ns .. jid)
      redis.call('del', QlessJob.ns .. jid .. '-history')
    end
    redis.call('zremrangebyscore', 'ql:completed', 0, now - time)

    jids = redis.call('zrange', 'ql:completed', 0, (-1-count))
    for index, jid in ipairs(jids) do
      redis.call('del', QlessJob.ns .. jid)
      redis.call('del', QlessJob.ns .. jid .. '-history')
    end
    redis.call('zremrangebyrank', 'ql:completed', 0, (-1-count))

    return 'complete'
  end
end

local function clearOldFailedJobs(now)
  local timeOffset = Qless.config.get('jobs-failed-history')
  local expiredJids = redis.call('zrangebyscore', 'ql:failed-jobs-list', 0, now - timeOffset)

  for index, jid in ipairs(expiredJids) do
    local queue = redis.call('hget', QlessJob.ns .. jid, 'queue')
    local jobData = redis.call('hget', QlessJob.ns .. jid, 'failure')

    local jidGroup = nil
    if type(jobData) == 'string' then
      jidGroup = cjson.decode(jobData).group
    end

    local deletedJobs = redis.call('del', QlessJob.ns .. jid)
    redis.call('del', QlessJob.ns .. jid .. '-history')

    if (deletedJobs > 0) then
      local bin = now - (now % 86400)
      redis.call('hincrby', 'ql:s:stats:' .. bin .. ':' .. queue, 'failed', (-1 * deletedJobs))

      if (jidGroup ~= nil) then
        redis.call('lrem', 'ql:f:' .. jidGroup, 0, jid)
        if redis.call('llen', 'ql:f:' .. jidGroup) == 0 then
          redis.call('srem', 'ql:failures', jidGroup)
        end
      end
    end

  end
  redis.call('zremrangebyscore', 'ql:failed-jobs-list', 0, now - timeOffset)
end

function QlessJob:fail(now, worker, group, message, data)
  local worker  = assert(worker           , 'Fail(): Arg "worker" missing')
  local group   = assert(group            , 'Fail(): Arg "group" missing')
  local message = assert(message          , 'Fail(): Arg "message" missing')

  local bin = now - (now % 86400)

  if data then
    data = cjson.decode(data)
  end

  local queue, state, oldworker = unpack(redis.call(
    'hmget', QlessJob.ns .. self.jid, 'queue', 'state', 'worker'))

  if not state then
    error('Fail(): Job ' .. self.jid .. 'does not exist')
  elseif state ~= 'running' then
    error('Fail(): Job ' .. self.jid .. 'not currently running: ' .. state)
  elseif worker ~= oldworker then
    error('Fail(): Job ' .. self.jid .. ' running with another worker: ' ..
      oldworker)
  end

  Qless.publish('log', cjson.encode({
    jid     = self.jid,
    event   = 'failed',
    worker  = worker,
    group   = group,
    message = message
  }))

  if redis.call('zscore', 'ql:tracked', self.jid) ~= false then
    Qless.publish('failed', self.jid)
  end

  redis.call('zrem', 'ql:w:' .. worker .. ':jobs', self.jid)

  self:history(now, 'failed', {worker = worker, group = group})

  redis.call('hincrby', 'ql:s:stats:' .. bin .. ':' .. queue, 'failures', 1)
  redis.call('hincrby', 'ql:s:stats:' .. bin .. ':' .. queue, 'failed'  , 1)

  local queue_obj = Qless.queue(queue)
  queue_obj.work.remove(self.jid)
  queue_obj.locks.remove(self.jid)
  queue_obj.scheduled.remove(self.jid)

  if data then
    redis.call('hset', QlessJob.ns .. self.jid, 'data', cjson.encode(data))
  end

  redis.call('hmset', QlessJob.ns .. self.jid,
    'state', 'failed',
    'worker', '',
    'expires', '',
    'failure', cjson.encode({
      ['group']   = group,
      ['message'] = message,
      ['when']    = math.floor(now),
      ['worker']  = worker
    }))

  redis.call('sadd', 'ql:failures', group)
  redis.call('lpush', 'ql:f:' .. group, self.jid)

  -- store failed jobs time
  redis.call('zadd', 'ql:failed-jobs-list', now, self.jid)

  clearOldFailedJobs(now)

  return self.jid
end

function QlessJob:retry(now, queue, worker, delay, group, message)
  assert(queue , 'Retry(): Arg "queue" missing')
  assert(worker, 'Retry(): Arg "worker" missing')
  delay = assert(tonumber(delay or 0),
    'Retry(): Arg "delay" not a number: ' .. tostring(delay))

  local oldqueue, state, retries, oldworker, failure = unpack(
    redis.call('hmget', QlessJob.ns .. self.jid, 'queue', 'state',
      'retries', 'worker', 'failure'))

  if oldworker == false then
    error('Retry(): Job ' .. self.jid .. ' does not exist')
  elseif state ~= 'running' then
    error('Retry(): Job ' .. self.jid .. ' is not currently running: ' ..
      state)
  elseif oldworker ~= worker then
    error('Retry(): Job ' .. self.jid ..
      ' has been given to another worker: ' .. oldworker)
  end

  local remaining = tonumber(redis.call(
    'hincrby', QlessJob.ns .. self.jid, 'remaining', -1))
  redis.call('hdel', QlessJob.ns .. self.jid, 'grace')

  Qless.queue(oldqueue).locks.remove(self.jid)

  redis.call('zrem', 'ql:w:' .. worker .. ':jobs', self.jid)

  if remaining < 0 then
    local group = group or 'failed-retries-' .. queue
    self:history(now, 'failed', {['group'] = group})

    redis.call('hmset', QlessJob.ns .. self.jid, 'state', 'failed',
      'worker', '',
      'expires', '')
    if group ~= nil and message ~= nil then
      redis.call('hset', QlessJob.ns .. self.jid,
        'failure', cjson.encode({
          ['group']   = group,
          ['message'] = message,
          ['when']    = math.floor(now),
          ['worker']  = worker
        })
      )
    else
      redis.call('hset', QlessJob.ns .. self.jid,
        'failure', cjson.encode({
          ['group']   = group,
          ['message'] =
          'Job exhausted retries in queue "' .. oldqueue .. '"',
          ['when']    = now,
          ['worker']  = unpack(self:data('worker'))
        }))
    end

    redis.call('sadd', 'ql:failures', group)
    redis.call('lpush', 'ql:f:' .. group, self.jid)

    redis.call('zadd', 'ql:failed-jobs-list', now, self.jid)

    clearOldFailedJobs(now)

    local bin = now - (now % 86400)
    redis.call('hincrby', 'ql:s:stats:' .. bin .. ':' .. queue, 'failures', 1)
    redis.call('hincrby', 'ql:s:stats:' .. bin .. ':' .. queue, 'failed'  , 1)

  else
    local queue_obj = Qless.queue(queue)
    if delay > 0 then
      queue_obj.scheduled.add(now + delay, self.jid)
      redis.call('hset', QlessJob.ns .. self.jid, 'state', 'scheduled')
    else
      queue_obj.work.add(now, self.jid)
      redis.call('hset', QlessJob.ns .. self.jid, 'state', 'waiting')
    end

    if group ~= nil and message ~= nil then
      redis.call('hset', QlessJob.ns .. self.jid,
        'failure', cjson.encode({
          ['group']   = group,
          ['message'] = message,
          ['when']    = math.floor(now),
          ['worker']  = worker
        })
      )
    end
  end

  return math.floor(remaining)
end

function QlessJob:depends(now, command, ...)
  errorMethodNotSupported('QlessJob:depends')
end

function QlessJob:heartbeat(now, worker, data)
  assert(worker, 'Heatbeat(): Arg "worker" missing')

  local queue = redis.call('hget', QlessJob.ns .. self.jid, 'queue') or ''
  local expires = now + tonumber(
    Qless.config.get(queue .. '-heartbeat') or
      Qless.config.get('heartbeat', 60))

  if data then
    data = cjson.decode(data)
  end

  local job_worker, state = unpack(
    redis.call('hmget', QlessJob.ns .. self.jid, 'worker', 'state'))
  if job_worker == false then
    error('Heartbeat(): Job ' .. self.jid .. ' does not exist')
  elseif state ~= 'running' then
    error(
      'Heartbeat(): Job ' .. self.jid .. ' not currently running: ' .. state)
  elseif job_worker ~= worker or #job_worker == 0 then
    error(
      'Heartbeat(): Job ' .. self.jid ..
        ' given out to another worker: ' .. job_worker)
  else
    if data then
      redis.call('hmset', QlessJob.ns .. self.jid, 'expires',
        expires, 'worker', worker, 'data', cjson.encode(data))
    else
      redis.call('hmset', QlessJob.ns .. self.jid,
        'expires', expires, 'worker', worker)
    end

    redis.call('zadd', 'ql:w:' .. worker .. ':jobs', expires, self.jid)

    redis.call('zadd', 'ql:workers', now, worker)

    local queue = Qless.queue(
      redis.call('hget', QlessJob.ns .. self.jid, 'queue'))
    queue.locks.add(expires, self.jid)
    return expires
  end
end

function QlessJob:priority(priority)
  -- it does not trigger error because it's used when abstract job is creating
  return priority;
end

function QlessJob:update(data)
  local tmp = {}
  for k, v in pairs(data) do
    table.insert(tmp, k)
    table.insert(tmp, v)
  end
  redis.call('hmset', QlessJob.ns .. self.jid, unpack(tmp))
end

function QlessJob:timeout(now)
  local queue_name, state, worker = unpack(redis.call('hmget',
    QlessJob.ns .. self.jid, 'queue', 'state', 'worker'))
  if queue_name == nil or queue_name == false then
    error('Timeout(): Job ' .. self.jid .. ' does not exist')
  elseif state ~= 'running' then
    error('Timeout(): Job ' .. self.jid .. ' not running')
  else
    self:history(now, 'timed-out')
    local queue = Qless.queue(queue_name)
    queue.locks.remove(self.jid)
    queue.work.add(now, self.jid)
    redis.call('hmset', QlessJob.ns .. self.jid,
      'state', 'stalled', 'expires', 0)
    local encoded = cjson.encode({
      jid    = self.jid,
      event  = 'lock_lost',
      worker = worker
    })
    Qless.publish('w:' .. worker, encoded)
    Qless.publish('log', encoded)
    return queue_name
  end
end

function QlessJob:exists()
  return redis.call('exists', QlessJob.ns .. self.jid) == 1
end

function QlessJob:history(now, what, item)
  local history = redis.call('hget', QlessJob.ns .. self.jid, 'history')
  if history then
    history = cjson.decode(history)
    for i, value in ipairs(history) do
      redis.call('rpush', QlessJob.ns .. self.jid .. '-history',
        cjson.encode({math.floor(value.put), 'put', {q = value.q}}))

      if value.popped then
        redis.call('rpush', QlessJob.ns .. self.jid .. '-history',
          cjson.encode({math.floor(value.popped), 'popped',
            {worker = value.worker}}))
      end

      if value.failed then
        redis.call('rpush', QlessJob.ns .. self.jid .. '-history',
          cjson.encode(
            {math.floor(value.failed), 'failed', nil}))
      end

      if value.done then
        redis.call('rpush', QlessJob.ns .. self.jid .. '-history',
          cjson.encode(
            {math.floor(value.done), 'done', nil}))
      end
    end
    redis.call('hdel', QlessJob.ns .. self.jid, 'history')
  end

  if what == nil then
    local response = {}
    for i, value in ipairs(redis.call('lrange',
      QlessJob.ns .. self.jid .. '-history', 0, -1)) do
      value = cjson.decode(value)
      local dict = value[3] or {}
      dict['when'] = value[1]
      dict['what'] = value[2]
      table.insert(response, dict)
    end
    return response
  else
    local count = tonumber(Qless.config.get('max-job-history', 100))
    if count > 0 then
      local obj = redis.call('lpop', QlessJob.ns .. self.jid .. '-history')
      redis.call('ltrim', QlessJob.ns .. self.jid .. '-history', -count + 2, -1)
      if obj ~= nil and obj ~= false then
        redis.call('lpush', QlessJob.ns .. self.jid .. '-history', obj)
      end
    end
    return redis.call('rpush', QlessJob.ns .. self.jid .. '-history',
      cjson.encode({math.floor(now), what, item}))
  end
end

function Qless.queue(name)
  assert(name, 'Queue(): no queue name provided')
  local queue = {}
  setmetatable(queue, QlessQueue)
  queue.name = name

  queue.completed = {
    peek = function(offset, count)
      if count == 0 then
        return {}
      end
      if offset == nil then
        offset = 0
      end
      local jids = {}
      for index, jid in ipairs(redis.call(
        'zrevrange', queue:prefix('completed'), offset, count - 1)) do
        table.insert(jids, jid)
      end
      return jids
    end, remove = function(...)
      if #arg > 0 then
        return redis.call('zrem', queue:prefix('completed'), unpack(arg))
      end
    end, add = function(now, jid)

      local count = Qless.config.get('jobs-history-count')
      local time  = Qless.config.get('jobs-history')

      count = tonumber(count or 50000)
      time  = tonumber(time  or 7 * 24 * 60 * 60)

      redis.call('zremrangebyscore', queue:prefix('completed'), 0, now - time)

      redis.call('zremrangebyrank', queue:prefix('completed'), 0, (-1-count))

      redis.call('zremrangebyscore', 'ql:tracked', 0, now - time)

      return redis.call('zadd',
        queue:prefix('completed'), now, jid)
    end, score = function(jid)
      return redis.call('zscore', queue:prefix('completed'), jid)
    end, length = function()
      return redis.call('zcard', queue:prefix('completed'))
    end
  }

  queue.work = {
    peek = function(offset, count)
      if count == 0 then
        return {}
      end
      local indexStart = offset
      if indexStart == nil then
        indexStart = 0
      end
      local jids = {}
      local indexEnd = indexStart + count - 1
      for index, jid in ipairs(
        redis.call('zrevrange', queue:prefix('work'), indexStart, indexEnd)
      ) do
        table.insert(jids, jid)
      end
      return jids
    end, remove = function(...)
      if #arg > 0 then
        return redis.call('zrem', queue:prefix('work'), unpack(arg))
      end
    end, add = function(now, jid)
      local priority = 0
      return redis.call('zadd',
        queue:prefix('work'), priority, jid)
    end, score = function(jid)
      return redis.call('zscore', queue:prefix('work'), jid)
    end, length = function()
      return redis.call('zcard', queue:prefix('work'))
    end
  }

  queue.locks = {
    expired = function(now, offset, count)
      return redis.call('zrangebyscore',
        queue:prefix('locks'), '-inf', now, 'LIMIT', offset, count)
    end, peek = function(now, offset, count)
      return redis.call('zrangebyscore', queue:prefix('locks'),
        now, '+inf', 'LIMIT', offset, count)
    end, add = function(expires, jid)
      redis.call('zadd', queue:prefix('locks'), expires, jid)
    end, remove = function(...)
      if #arg > 0 then
        return redis.call('zrem', queue:prefix('locks'), unpack(arg))
      end
    end, running = function(now)
      return redis.call('zcount', queue:prefix('locks'), now, '+inf')
    end, length = function(now)
      if now then
        return redis.call('zcount', queue:prefix('locks'), 0, now)
      else
        return redis.call('zcard', queue:prefix('locks'))
      end
    end
  }

  queue.depends = {
    peek = function(now, offset, count)
      errorMethodNotSupported('queue.depends.peek')
    end, add = function(now, jid)
      errorMethodNotSupported('queue.depends.add')
    end, remove = function(...)
      errorMethodNotSupported('queue.depends.remove')
    end, length = function()
      errorMethodNotSupported('queue.depends.length')
    end
  }

  queue.scheduled = {
    peek = function(now, offset, count)
      return redis.call('zrange',
        queue:prefix('scheduled'), offset, offset + count - 1)
    end, ready = function(now, offset, count)
      return redis.call('zrangebyscore',
        queue:prefix('scheduled'), 0, now, 'LIMIT', offset, count)
    end, add = function(when, jid)
      redis.call('zadd', queue:prefix('scheduled'), when, jid)
    end, remove = function(...)
      if #arg > 0 then
        return redis.call('zrem', queue:prefix('scheduled'), unpack(arg))
      end
    end, length = function()
      return redis.call('zcard', queue:prefix('scheduled'))
    end
  }

  queue.recurring = {
    peek = function(now, offset, count)
      errorMethodNotSupported('queue.recurring.peek')
    end, ready = function(now, offset, count)
      errorMethodNotSupported('queue.recurring.ready')
    end, add = function(when, jid)
      errorMethodNotSupported('queue.recurring.add')
    end, remove = function(...)
      errorMethodNotSupported('queue.recurring.remove')
    end, update = function(increment, jid)
      errorMethodNotSupported('queue.recurring.update')
    end, score = function(jid)
      errorMethodNotSupported('queue.recurring.score')
    end, length = function()
      errorMethodNotSupported('queue.recurring.length')
    end
  }
  return queue
end

function QlessQueue:prefix(group)
  if group then
    return QlessQueue.ns..self.name..'-'..group
  else
    return QlessQueue.ns..self.name
  end
end

function QlessQueue:stats(now, date)
  date = assert(tonumber(date),
    'Stats(): Arg "date" missing or not a number: '.. (date or 'nil'))

  local bin = date - (date % 86400)

  local histokeys = {
    's0','s1','s2','s3','s4','s5','s6','s7','s8','s9','s10','s11','s12','s13','s14','s15','s16','s17','s18','s19','s20','s21','s22','s23','s24','s25','s26','s27','s28','s29','s30','s31','s32','s33','s34','s35','s36','s37','s38','s39','s40','s41','s42','s43','s44','s45','s46','s47','s48','s49','s50','s51','s52','s53','s54','s55','s56','s57','s58','s59',
    'm1','m2','m3','m4','m5','m6','m7','m8','m9','m10','m11','m12','m13','m14','m15','m16','m17','m18','m19','m20','m21','m22','m23','m24','m25','m26','m27','m28','m29','m30','m31','m32','m33','m34','m35','m36','m37','m38','m39','m40','m41','m42','m43','m44','m45','m46','m47','m48','m49','m50','m51','m52','m53','m54','m55','m56','m57','m58','m59',
    'h1','h2','h3','h4','h5','h6','h7','h8','h9','h10','h11','h12','h13','h14','h15','h16','h17','h18','h19','h20','h21','h22','h23',
    'd1','d2','d3','d4','d5','d6'
  }

  local mkstats = function(name, bin, queue)
    local results = {}

    local key = 'ql:s:' .. name .. ':' .. bin .. ':' .. queue
    local count, mean, vk = unpack(redis.call('hmget', key, 'total', 'mean', 'vk'))

    count = tonumber(count) or 0
    mean  = tonumber(mean) or 0
    vk    = tonumber(vk)

    results.count     = count or 0
    results.mean      = mean  or 0
    results.histogram = {}

    if not count then
      results.std = 0
    else
      if count > 1 then
        results.std = math.sqrt(vk / (count - 1))
      else
        results.std = 0
      end
    end

    local histogram = redis.call('hmget', key, unpack(histokeys))
    for i=1,#histokeys do
      table.insert(results.histogram, tonumber(histogram[i]) or 0)
    end
    return results
  end

  local retries, failed, failures = unpack(redis.call('hmget', 'ql:s:stats:' .. bin .. ':' .. self.name, 'retries', 'failed', 'failures'))
  return {
    retries  = tonumber(retries  or 0),
    failed   = tonumber(failed   or 0),
    failures = tonumber(failures or 0),
    wait     = mkstats('wait', bin, self.name),
    run      = mkstats('run' , bin, self.name)
  }
end

function QlessQueue:peek(now, count)
  count = assert(tonumber(count),
    'Peek(): Arg "count" missing or not a number: ' .. tostring(count))

  local jids = self.locks.expired(now, 0, count)

  self:check_scheduled(now, count - #jids)

  tableUtils.extend(jids, self.work.peek(0, count - #jids))

  return jids
end

function QlessQueue:paused()
  return redis.call('sismember', 'ql:paused_queues', self.name) == 1
end

QlessQueue.pause = function(now, ...)
  redis.call('sadd', 'ql:paused_queues', unpack(arg))
end

QlessQueue.unpause = function(...)
  redis.call('srem', 'ql:paused_queues', unpack(arg))
end

function QlessQueue:pop(now, worker, count)
  assert(worker, 'Pop(): Arg "worker" missing')
  count = assert(tonumber(count),
    'Pop(): Arg "count" missing or not a number: ' .. tostring(count))

  local expires = now + tonumber(
    Qless.config.get(self.name .. '-heartbeat') or
      Qless.config.get('heartbeat', 60))

  if self:paused() then
    return {}
  end

  redis.call('zadd', 'ql:workers', now, worker)

  local max_concurrency = tonumber(
    Qless.config.get(self.name .. '-max-concurrency', 0))

  if max_concurrency > 0 then
    local allowed = math.max(0, max_concurrency - self.locks.running(now))
    count = math.min(allowed, count)
    if count == 0 then
      return {}
    end
  end

  local jids = self:invalidate_locks(now, count)

  self:check_scheduled(now, count - #jids)

  tableUtils.extend(jids, self.work.peek(0, count - #jids))

  local state
  for index, jid in ipairs(jids) do
    local job = Qless.job(jid)

    local statePacked = job:data('state');

    state = '';
    if type(statePacked) == 'table' then
      state = unpack(job:data('state'))
    end

    job:history(now, 'popped', {worker = worker})

    local time = tonumber(
      redis.call('hget', QlessJob.ns .. jid, 'time') or now)
    local waiting = now - time
    self:stat(now, 'wait', waiting)
    redis.call('hset', QlessJob.ns .. jid,
      'time', string.format("%.20f", now))

    redis.call('zadd', 'ql:w:' .. worker .. ':jobs', expires, jid)

    job:update({
      worker  = worker,
      expires = expires,
      state   = 'running'
    })

    self.locks.add(expires, jid)

    local tracked = redis.call('zscore', 'ql:tracked', jid) ~= false
    if tracked then
      Qless.publish('popped', jid)
    end
  end

  self.work.remove(unpack(jids))

  return jids
end

function QlessQueue:popByJid(now ,jid, worker)
  assert(jid, 'Pop(): Arg "jid" missing')
  assert(worker, 'Pop(): Arg "worker" missing')

  local expires = now + tonumber(
          Qless.config.get(self.name .. '-heartbeat') or
                  Qless.config.get('heartbeat', 60))

  if self:paused() then
    return {}
  end

  jid = self:invalidate_lock_jid(now, jid)

  local job = Qless.job(jid)

  local state = unpack(job:data('state'))

  if (state ~= 'waiting') then
    return {}
  end

  redis.call('zadd', 'ql:workers', now, worker)

  job:history(now, 'popped', {worker = worker})

  local time = tonumber(
          redis.call('hget', QlessJob.ns .. jid, 'time') or now)
  local waiting = now - time
  self:stat(now, 'wait', waiting)
  redis.call('hset', QlessJob.ns .. jid,
          'time', string.format("%.20f", now))

  redis.call('zadd', 'ql:w:' .. worker .. ':jobs', expires, jid)

  job:update({
    worker  = worker,
    expires = expires,
    state   = 'running'
  })

  self.locks.add(expires, jid)

  local tracked = redis.call('zscore', 'ql:tracked', jid) ~= false

  if tracked then
    Qless.publish('popped', jid)
  end

  self.work.remove(jid)

  return Qless.job(jid):data()
end

function QlessQueue:stat(now, stat, val)
  local bin = now - (now % 86400)
  local key = 'ql:s:' .. stat .. ':' .. bin .. ':' .. self.name

  local count, mean, vk = unpack(
    redis.call('hmget', key, 'total', 'mean', 'vk'))

  count = count or 0
  if count == 0 then
    mean  = val
    vk    = 0
    count = 1
  else
    count = count + 1
    local oldmean = mean
    mean  = mean + (val - mean) / count
    vk    = vk + (val - mean) * (val - oldmean)
  end

  val = math.floor(val)
  if val < 60 then -- seconds
    redis.call('hincrby', key, 's' .. val, 1)
  elseif val < 3600 then -- minutes
    redis.call('hincrby', key, 'm' .. math.floor(val / 60), 1)
  elseif val < 86400 then -- hours
    redis.call('hincrby', key, 'h' .. math.floor(val / 3600), 1)
  else -- days
    redis.call('hincrby', key, 'd' .. math.floor(val / 86400), 1)
  end
  redis.call('hmset', key, 'total', count, 'mean', mean, 'vk', vk)
end

function QlessQueue:put(now, worker, jid, klass, raw_data, delay, ...)
  assert(jid  , 'Put(): Arg "jid" missing')
  assert(klass, 'Put(): Arg "klass" missing')
  local data = assert(cjson.decode(raw_data),
    'Put(): Arg "data" missing or not JSON: ' .. tostring(raw_data))
  delay = assert(tonumber(delay),
    'Put(): Arg "delay" not a number: ' .. tostring(delay))

  if #arg % 2 == 1 then
    error('Odd number of additional args: ' .. tostring(arg))
  end
  local options = {}
  for i = 1, #arg, 2 do options[arg[i]] = arg[i + 1] end

  local job = Qless.job(jid)
  local oldqueue, state, failure, retries, oldworker =
  unpack(redis.call('hmget', QlessJob.ns .. jid, 'queue', 'state', 'failure',
    'retries', 'worker'))

  retries  = assert(tonumber(options['retries']  or retries or 5) ,
    'Put(): Arg "retries" not a number: ' .. tostring(options['retries']))

  Qless.publish('log', cjson.encode({
    jid   = jid,
    event = 'put',
    queue = self.name
  }))

  job:history(now, 'put', {q = self.name})

  if oldqueue then
    local queue_obj = Qless.queue(oldqueue)
    queue_obj.work.remove(jid)
    queue_obj.locks.remove(jid)
    queue_obj.scheduled.remove(jid)
  end

  if oldworker and oldworker ~= '' then
    redis.call('zrem', 'ql:w:' .. oldworker .. ':jobs', jid)
    if oldworker ~= worker then
      local encoded = cjson.encode({
        jid    = jid,
        event  = 'lock_lost',
        worker = oldworker
      })
      Qless.publish('w:' .. oldworker, encoded)
      Qless.publish('log', encoded)
    end
  end

  if state == 'complete' then
    redis.call('zrem', 'ql:completed', jid)
  end

  if state == 'failed' then
    failure = cjson.decode(failure)
    redis.call('lrem', 'ql:f:' .. failure.group, 0, jid)
    if redis.call('llen', 'ql:f:' .. failure.group) == 0 then
      redis.call('srem', 'ql:failures', failure.group)
    end
    local bin = failure.when - (failure.when % 86400)
    redis.call('hincrby', 'ql:s:stats:' .. bin .. ':' .. self.name, 'failed'  , -1)
  end

  redis.call('hmset', QlessJob.ns .. jid,
    'jid'      , jid,
    'klass'    , klass,
    'data'     , raw_data,
    'priority' , 0,
    'tags'     , '{}',
    'state'    , ((delay > 0) and 'scheduled') or 'waiting',
    'worker'   , '',
    'expires'  , 0,
    'queue'    , self.name,
    'retries'  , retries,
    'remaining', retries,
    'time'     , string.format("%.20f", now))

  if delay > 0 then
    self.scheduled.add(now + delay, jid)
  else
    self.work.add(now, jid)
  end

  if redis.call('zscore', 'ql:queues', self.name) == false then
    redis.call('zadd', 'ql:queues', now, self.name)
  end

  if redis.call('zscore', 'ql:tracked', jid) ~= false then
    Qless.publish('put', jid)
  end

  return jid
end

function QlessQueue:unfail(now, group, count)
  assert(group, 'Unfail(): Arg "group" missing')
  count = assert(tonumber(count or 25),
    'Unfail(): Arg "count" not a number: ' .. tostring(count))

  local jids = redis.call('lrange', 'ql:f:' .. group, -count, -1)

  local toinsert = {}
  for index, jid in ipairs(jids) do
    local job = Qless.job(jid)
    local data = job:data()
    job:history(now, 'put', {q = self.name})
    redis.call('hmset', QlessJob.ns .. data.jid,
      'state'    , 'waiting',
      'worker'   , '',
      'expires'  , 0,
      'queue'    , self.name,
      'remaining', data.retries or 5)
    self.work.add(now, data.jid)
  end

  redis.call('ltrim', 'ql:f:' .. group, 0, -count - 1)
  if (redis.call('llen', 'ql:f:' .. group) == 0) then
    redis.call('srem', 'ql:failures', group)
  end

  return #jids
end

function QlessQueue:recur(now, jid, klass, raw_data, spec, ...)
  errorMethodNotSupported('QlessQueue:recur')
end

function QlessQueue:length()
  return  self.locks.length() + self.work.length() + self.scheduled.length()
end

function QlessQueue:check_scheduled(now, count)
  local scheduled = self.scheduled.ready(now, 0, count)
  for index, jid in ipairs(scheduled) do
    self.work.add(now, jid)
    self.scheduled.remove(jid)

    redis.call('hset', QlessJob.ns .. jid, 'state', 'waiting')
  end
end

function QlessQueue:invalidate_lock_jid(now, jid)
  local worker, failure = unpack(
          redis.call('hmget', QlessJob.ns .. jid, 'worker', 'failure'))

  if type(worker) ~= 'string' then
    worker = '';
  end

  redis.call('zrem', 'ql:w:' .. worker .. ':jobs', jid)

  local grace_period = tonumber(Qless.config.get('grace-period'))

  local courtesy_sent = tonumber(
          redis.call('hget', QlessJob.ns .. jid, 'grace') or 0)

  local send_message = (courtesy_sent ~= 1)
  local invalidate   = not send_message

  if grace_period <= 0 then
    send_message = true
    invalidate   = true
  end

  if send_message then
    if redis.call('zscore', 'ql:tracked', jid) ~= false then
      Qless.publish('stalled', jid)
    end
    Qless.job(jid):history(now, 'timed-out')
    redis.call('hset', QlessJob.ns .. jid, 'grace', 1)

    local encoded = cjson.encode({
      jid    = jid,
      event  = 'lock_lost',
      worker = worker
    })
    Qless.publish('w:' .. worker, encoded)
    Qless.publish('log', encoded)
    self.locks.add(now + grace_period, jid)

    local bin = now - (now % 86400)
    redis.call('hincrby',
            'ql:s:stats:' .. bin .. ':' .. self.name, 'retries', 1)
  end

  if invalidate then
    redis.call('hdel', QlessJob.ns .. jid, 'grace', 0)

    local remaining = tonumber(redis.call(
            'hincrby', QlessJob.ns .. jid, 'remaining', -1))
  end

  return jid
end

function QlessQueue:invalidate_locks(now, count)
  local jids = {}
  for index, jid in ipairs(self.locks.expired(now, 0, count)) do
    local worker, failure = unpack(
      redis.call('hmget', QlessJob.ns .. jid, 'worker', 'failure'))

    if type(worker) ~= 'string' then
      worker = '';
    end

    redis.call('zrem', 'ql:w:' .. worker .. ':jobs', jid)

    local grace_period = tonumber(Qless.config.get('grace-period'))

    local courtesy_sent = tonumber(
      redis.call('hget', QlessJob.ns .. jid, 'grace') or 0)

    local send_message = (courtesy_sent ~= 1)
    local invalidate   = not send_message

    if grace_period <= 0 then
      send_message = true
      invalidate   = true
    end

    if send_message then
      if redis.call('zscore', 'ql:tracked', jid) ~= false then
        Qless.publish('stalled', jid)
      end
      Qless.job(jid):history(now, 'timed-out')
      redis.call('hset', QlessJob.ns .. jid, 'grace', 1)

      local encoded = cjson.encode({
        jid    = jid,
        event  = 'lock_lost',
        worker = worker
      })
      Qless.publish('w:' .. worker, encoded)
      Qless.publish('log', encoded)
      self.locks.add(now + grace_period, jid)

      local bin = now - (now % 86400)
      redis.call('hincrby',
        'ql:s:stats:' .. bin .. ':' .. self.name, 'retries', 1)
    end

    if invalidate then
      redis.call('hdel', QlessJob.ns .. jid, 'grace', 0)

      local remaining = tonumber(redis.call(
        'hincrby', QlessJob.ns .. jid, 'remaining', -1))

      if remaining < 0 then
        self.work.remove(jid)
        self.locks.remove(jid)
        self.scheduled.remove(jid)

        local group = 'failed-retries-' .. Qless.job(jid):data()['queue']
        local job = Qless.job(jid)
        job:history(now, 'failed', {group = group})
        redis.call('hmset', QlessJob.ns .. jid,
          'state', 'failed',
          'worker', '',
          'expires', '',
          'failure', cjson.encode({
            ['group']   = group,
            ['message'] =
            'Job exhausted retries in queue "' .. self.name .. '"',
            ['when']    = now,
            ['worker']  = unpack(job:data('worker'))
          })
        )

        redis.call('sadd', 'ql:failures', group)
        redis.call('lpush', 'ql:f:' .. group, jid)

        if redis.call('zscore', 'ql:tracked', jid) ~= false then
          Qless.publish('failed', jid)
        end
        Qless.publish('log', cjson.encode({
          jid     = jid,
          event   = 'failed',
          group   = group,
          worker  = worker,
          message =
          'Job exhausted retries in queue "' .. self.name .. '"'
        }))

        local bin = now - (now % 86400)
        redis.call('hincrby',
          'ql:s:stats:' .. bin .. ':' .. self.name, 'failures', 1)
        redis.call('hincrby',
          'ql:s:stats:' .. bin .. ':' .. self.name, 'failed'  , 1)
        redis.call('zadd', 'ql:failed-jobs-list', now, jid)
        clearOldFailedJobs(now)
      else
        table.insert(jids, jid)
      end
    end
  end

  return jids
end

QlessQueue.deregister = function(...)
  redis.call('zrem', Qless.ns .. 'queues', unpack(arg))
end

QlessQueue.counts = function(now, name)
  if name then
    local queue = Qless.queue(name)
    local stalled = queue.locks.length(now)
    queue:check_scheduled(now, queue.scheduled.length())
    return {
      name      = name,
      waiting   = queue.work.length(),
      stalled   = stalled,
      running   = queue.locks.length() - stalled,
      scheduled = queue.scheduled.length(),
      depends   = 0,
      recurring = 0,
      paused    = queue:paused()
    }
  else
    local queues = redis.call('zrange', 'ql:queues', 0, -1)
    local response = {}
    for index, qname in ipairs(queues) do
      table.insert(response, QlessQueue.counts(now, qname))
    end
    return response
  end
end

function QlessRecurringJob:data()
  return nil
end

function QlessRecurringJob:update(now, ...)
  errorMethodNotSupported('QlessRecurringJob:update')
end

function QlessRecurringJob:tag(...)
  errorMethodNotSupported('QlessRecurringJob:tag')
end

function QlessRecurringJob:untag(...)
  errorMethodNotSupported('QlessRecurringJob:untag')
end

function QlessRecurringJob:unrecur()
  errorMethodNotSupported('QlessRecurringJob:unrecur')
end

QlessWorker.deregister = function(...)
  return redis.call('zrem', 'ql:workers', unpack(arg))
end

QlessWorker.counts = function(now, worker, start, last)
  local interval = tonumber(Qless.config.get('max-worker-age', 86400))

  local workers  = redis.call('zrangebyscore', 'ql:workers', 0, now - interval)
  for index, worker in ipairs(workers) do
    redis.call('del', 'ql:w:' .. worker .. ':jobs')
  end

  redis.call('zremrangebyscore', 'ql:workers', 0, now - interval)

  if string.len(worker or '') > 0 then
    return {
      jobs    = redis.call('zrevrangebyscore', 'ql:w:' .. worker .. ':jobs', now + 8640000, now),
      stalled = redis.call('zrevrangebyscore', 'ql:w:' .. worker .. ':jobs', now, 0)
    }
  else
    local response = {}
    local workers = redis.call('zrevrange', 'ql:workers', start or 0, last or -1)
    for index, worker in ipairs(workers) do
      table.insert(response, {
        name    = worker,
        jobs    = redis.call('zcount', 'ql:w:' .. worker .. ':jobs', now, now + 8640000),
        stalled = redis.call('zcount', 'ql:w:' .. worker .. ':jobs', 0, now)
      })
    end
    return response
  end
end

QlessWorker.jobs = function(worker, minScore)
  minScore = minScore or 0
  if worker == nil then
    return nil
  end
  if minScore == 0 then
    return redis.call('zrange', 'ql:w:' .. worker .. ':jobs', 0, -1)
  else
    return redis.call('zrangebyscore', 'ql:w:' .. worker .. ':jobs', minScore, '+inf')
  end
end

local QlessAPI = {}

QlessAPI.get = function(now, jid)
  local data = Qless.job(jid):data()
  if not data then
    return nil
  end
  return cjson.encode(data)
end

QlessAPI.multiget = function(now, ...)
  local results = {}
  for i, jid in ipairs(arg) do
    table.insert(results, Qless.job(jid):data())
  end
  return cjson.encode(results)
end

QlessAPI['config.get'] = function(now, key)
  if not key then
    return cjson.encode(Qless.config.get(key))
  else
    return Qless.config.get(key)
  end
end

QlessAPI['config.set'] = function(now, key, value)
  return Qless.config.set(key, value)
end

QlessAPI['config.unset'] = function(now, key)
  return Qless.config.unset(key)
end

QlessAPI.queues = function(now, queue)
  return cjson.encode(QlessQueue.counts(now, queue))
end

QlessAPI.complete = function(now, jid, worker, queue, data, ...)
  return Qless.job(jid):complete(now, worker, queue, data, unpack(arg))
end

QlessAPI.failed = function(now, group, start, limit)
  return cjson.encode(Qless.failed(group, start, limit))
end

QlessAPI.fail = function(now, jid, worker, group, message, data)
  return Qless.job(jid):fail(now, worker, group, message, data)
end

QlessAPI.jobs = function(now, state, ...)
  return Qless.jobs(now, state, unpack(arg))
end

QlessAPI.retry = function(now, jid, queue, worker, delay, group, message)
  return Qless.job(jid):retry(now, queue, worker, delay, group, message)
end

QlessAPI.depends = function(now, jid, command, ...)
  return Qless.job(jid):depends(now, command, unpack(arg))
end

QlessAPI.heartbeat = function(now, jid, worker, data)
  return Qless.job(jid):heartbeat(now, worker, data)
end

QlessAPI.workers = function(now, worker, start, last)
  return cjson.encode(QlessWorker.counts(now, worker, start, last))
end

QlessAPI.workerJobs = function(now, worker, minScore)
  return cjson.encode(QlessWorker.jobs(worker, minScore))
end

QlessAPI.workersCount = function(now)
  return redis.call('zcount', 'ql:workers', '-inf', '+inf')
end

QlessAPI.tracked = function (now)
  local tracked = redis.call('zrange', 'ql:tracked', 0, -1)
  return cjson.encode(tracked)
end

QlessAPI.track = function(now, command, jid)
  return cjson.encode(Qless.track(now, command, jid))
end

QlessAPI.tags = function(now, cursor, count)
  return cjson.encode(Qless.tags(cursor, count))
end

QlessAPI.tag = function(now, command, ...)
  return cjson.encode(Qless.tag(now, command, unpack(arg)))
end

QlessAPI.subscription = function(now, queue, command, topic)
  return cjson.encode(Qless.subscription(now, queue, command, topic))
end

QlessAPI.stats = function(now, queue, date)
  return cjson.encode(Qless.queue(queue):stats(now, date))
end

QlessAPI.priority = function(now, jid, priority)
  return Qless.job(jid):priority(priority)
end

QlessAPI.log = function(now, jid, message, data)
  assert(jid, "Log(): Argument 'jid' missing")
  assert(message, "Log(): Argument 'message' missing")
  if data then
    data = assert(cjson.decode(data),
      "Log(): Argument 'data' not cjson: " .. tostring(data))
  end

  local job = Qless.job(jid)
  assert(job:exists(), 'Log(): Job ' .. jid .. ' does not exist')
  job:history(now, message, data)
end

QlessAPI.peek = function(now, queue, count)
  local jids = Qless.queue(queue):peek(now, count)
  local response = {}
  for i, jid in ipairs(jids) do
    table.insert(response, Qless.job(jid):data())
  end
  return cjson.encode(response)
end

QlessAPI.pop = function(now, queue, worker, count)
  local jids = Qless.queue(queue):pop(now, worker, count)
  local response = {}
  for i, jid in ipairs(jids) do
    table.insert(response, Qless.job(jid):data())
  end
  return cjson.encode(response)
end

QlessAPI.popByJid = function(now, queue, jid, worker)
  local job = Qless.queue(queue):popByJid(now, jid, worker)
  local response = {}
  table.insert(response, job)
  return cjson.encode(response)
end

QlessAPI.pause = function(now, ...)
  return QlessQueue.pause(now, unpack(arg))
end

QlessAPI.unpause = function(now, ...)
  return QlessQueue.unpause(unpack(arg))
end

QlessAPI.cancel = function(now, ...)
  return Qless.cancel(unpack(arg))
end

QlessAPI.timeout = function(now, ...)
  for _, jid in ipairs(arg) do
    Qless.job(jid):timeout(now)
  end
end

QlessAPI.put = function(now, me, queue, jid, klass, data, delay, ...)
  return Qless.queue(queue):put(now, me, jid, klass, data, delay, unpack(arg))
end

QlessAPI.requeue = function(now, me, queue, jid, ...)
  local job = Qless.job(jid)
  assert(job:exists(), 'Requeue(): Job ' .. jid .. ' does not exist')
  return QlessAPI.put(now, me, queue, jid, unpack(arg))
end

QlessAPI.unfail = function(now, queue, group, count)
  return Qless.queue(queue):unfail(now, group, count)
end

QlessAPI.recur = function(now, queue, jid, klass, data, spec, ...)
  return Qless.queue(queue):recur(now, jid, klass, data, spec, unpack(arg))
end

QlessAPI.unrecur = function(now, jid)
  return Qless.recurring(jid):unrecur()
end

QlessAPI['recur.get'] = function(now, jid)
  local data = Qless.recurring(jid):data()
  if not data then
    return nil
  end
  return cjson.encode(data)
end

QlessAPI['recur.update'] = function(now, jid, ...)
  return Qless.recurring(jid):update(now, unpack(arg))
end

QlessAPI['recur.tag'] = function(now, jid, ...)
  return Qless.recurring(jid):tag(unpack(arg))
end

QlessAPI['recur.untag'] = function(now, jid, ...)
  return Qless.recurring(jid):untag(unpack(arg))
end

QlessAPI.length = function(now, queue)
  return Qless.queue(queue):length()
end

QlessAPI['worker.deregister'] = function(now, ...)
  return QlessWorker.deregister(unpack(arg))
end

QlessAPI['queue.forget'] = function(now, ...)
  QlessQueue.deregister(unpack(arg))
end

local command_name = assert(table.remove(ARGV, 1), 'Must provide a command')
local command = assert(QlessAPI[command_name], 'Unknown command ' .. command_name)

local now = tonumber(table.remove(ARGV, 1))
local now = assert(now, 'Arg "now" missing or not a number: ' .. (now or 'nil'))

return command(now, unpack(ARGV))
