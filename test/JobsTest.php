<?php

use Qless\Jobs;

require_once __DIR__ . '/QlessTest.php';

class JobsTest extends QlessTest {


    public function testNothing() {
        $c = new Qless\Client('192.168.50.130', 6381);
        $j = new Jobs($c);

        $r = $j->failed();
        $r = $j->failedForGroup('system:fatal');

        $job = $j['develop:1389810600_114'];
        $job = $j['develop:1389810600_114_2'];
        $jids = $j->get(['develop:1389810600_114', 'develop:1389810600_115']);
    }
}
 