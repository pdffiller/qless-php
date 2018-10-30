<?php

use Predis\Client as Redis;

require_once __DIR__ . '/bootstrap.php';

$redis = new Redis([
    'host' => REDIS_HOST,
    'port' => REDIS_PORT,
]);
$redis->connect();

sleep(2);

function payload($event)
{
    return json_encode([
        'jid' => 'jid-1',
        'worker' => 'test-worker',
        'event' => $event,
        'queue' => 'test-queue',
    ]);
}

$redis->publish('test:chan-1', payload('canceled'));
$redis->publish('test:chan-1', payload('completed'));
$redis->publish('test:chan-2', payload('failed'));
$redis->publish('test:chan-2', payload('lock_lost'));
$redis->publish('test:chan-3', payload('put'));
$redis->publish('test:chan-2', payload('another_event'));
$redis->publish('test:chan-4', payload('foo'));
