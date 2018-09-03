<?php

require_once 'bootstrap.php';

$redisConfig = [
    'host'    => REDIS_HOST,
    'port'    => REDIS_PORT,
    'timeout' => REDIS_TIMEOUT,
];

$redis = new Redis();
$redis->connect($redisConfig['host'], $redisConfig['port'], $redisConfig['timeout']);

sleep(2);

$redis->publish('chan-1', json_encode(['event' => 'event #1 to channel 1']));
$redis->publish('chan-1', json_encode(['event' => 'event #2 to channel 1']));
$redis->publish('chan-2', json_encode(['event' => 'event #3 to channel 2']));
$redis->publish('chan-2', json_encode(['event' => 'event #4 to channel 2']));
$redis->publish('chan-3', json_encode(['event' => 'event #5 to channel 3']));
$redis->publish('chan-2', json_encode(['event' => 'event #6 to channel 4']));
