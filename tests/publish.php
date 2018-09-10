<?php

use Qless\Events\Event;

require_once 'bootstrap.php';

$redisConfig = [
    'host'    => REDIS_HOST,
    'port'    => REDIS_PORT,
    'timeout' => REDIS_TIMEOUT,
];

$redis = new Redis();
$redis->connect($redisConfig['host'], $redisConfig['port'], $redisConfig['timeout']);

sleep(2);

$redis->publish('chan-1', json_encode(['event' => Event::CANCELED]));
$redis->publish('chan-1', json_encode(['event' => Event::COMPLETED]));
$redis->publish('chan-2', json_encode(['event' => Event::FAILED]));
$redis->publish('chan-2', json_encode(['event' => Event::LOCK_LOST]));
$redis->publish('chan-3', json_encode(['event' => Event::PUT]));
$redis->publish('chan-2', json_encode(['event' => Event::CONFIG_SET]));
$redis->publish('chan-4', json_encode(['event' => Event::CONFIG_UNSET]));
