<?php

use Predis\Connection\ConnectionException;
use Qless\Client;

require_once __DIR__ . '/bootstrap.php';

$client = new Client(
    [
        'host' => REDIS_HOST,
        'port' => REDIS_PORT,
    ]
);

$pubSubManager = $client->events;

$pid = posix_getpid();
array_shift($_SERVER['argv']);
$eventType = array_shift($_SERVER['argv']);
$expectedJID = array_shift($_SERVER['argv']);

\fprintf(STDERR, 'Waiting for Event type "%s" for jid "%s" in process %d'. PHP_EOL, $eventType, $expectedJID, $pid);

$pubSubManager->on(
    $eventType,
    static function ($jid) use ($eventType, $expectedJID, $pid, $pubSubManager) {
        \printf('%s: %s' . PHP_EOL, $eventType, $jid);
        \fprintf(STDERR, 'Got Event type "%s" for jid "%s" in process %d'. PHP_EOL, $eventType, $jid, $pid);
        if ($jid === $expectedJID) {
            $pubSubManager->stopListening();
            exit(0);
        }
    }
);

while (true) {
    try {
        $pubSubManager->listen();
    } catch (ConnectionException $exception) {
        \fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        \fwrite(STDERR, 'Reconnecting to Redis' . PHP_EOL);
        $client->reconnect();
        continue;
    } catch (\Throwable $exception) {
        \fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
