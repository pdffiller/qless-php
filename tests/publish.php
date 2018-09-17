<?php

use Qless\Events\QlessCoreEvent;

require_once 'bootstrap.php';

$redisConfig = [
    'host'    => REDIS_HOST,
    'port'    => REDIS_PORT,
    'timeout' => REDIS_TIMEOUT,
];

$redis = new Redis();
$redis->connect($redisConfig['host'], $redisConfig['port'], $redisConfig['timeout']);

sleep(2);

$redis->publish('chan-1', json_encode(['event' => QlessCoreEvent::CANCELED]));
$redis->publish('chan-1', json_encode(['event' => QlessCoreEvent::COMPLETED]));
$redis->publish('chan-2', json_encode(['event' => QlessCoreEvent::FAILED]));
$redis->publish('chan-2', json_encode(['event' => QlessCoreEvent::LOCK_LOST]));
$redis->publish('chan-3', json_encode(['event' => QlessCoreEvent::PUT]));
$redis->publish('chan-2', json_encode(['event' => QlessCoreEvent::CONFIG_SET]));
$redis->publish('chan-4', json_encode(['event' => EvQlessCoreEventent::CONFIG_UNSET]));
