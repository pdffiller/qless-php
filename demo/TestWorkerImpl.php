<?php
/**
 * Created by PhpStorm.
 * User: paul
 * Date: 11/4/13
 * Time: 12:14 PM
 */

class TestWorkerImpl {
    public function myPerformMethod($job){
        echo "here in worker performMethod\n\n";
        $job->complete();
    }

    public function myThrowMethod($job){
        echo "in throw job.\n";
        sleep(15);
        throw new \Exception("job exception message.");
    }

    public function exitMethod($job){
        sleep(5);
        exit(1);
    }
} 