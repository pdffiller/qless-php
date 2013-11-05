<?php
/**
 * Created by PhpStorm.
 * User: paul
 * Date: 10/30/13
 * Time: 2:58 PM
 */

namespace Qless;

include 'Qless.php';
include 'Job.php';


class Queue {

    private $name;
    private $client;
    private $workerName;

    public function __construct($name, $client){
        $this->name = $name;
        $this->client = $client;
    }

    /**
     *  * Either create a new job in the provided queue with the provided attributes,
    or move that job into that queue. If the job is being serviced by a worker,
    subsequent attempts by that worker to either `heartbeat` or `complete` the
    job should fail and return `false`.

    The `priority` argument should be negative to be run sooner rather than
    later, and positive if it's less important. The `tags` argument should be
    a JSON array of the tags associated with the instance and the `valid after`
    argument should be in how many seconds the instance should be considered
    actionable.'''
     * @param        $worker - the name for the worker or null.
     * @param        $klass - The class with the 'performMethod' specified in the data.
     * @param        $jid - specified job id, if null, will be generated.
     * @param        $data - array of parameters for job.
     * @param int    $priority
     * @param array  $tags
     * @param string $delay - specify delay to run job.
     * @param string $retries - number of retries allowed.
     * @param array  $depends
     *
     * @return mixed
     */
    public function put($worker, $klass, $jid, $data, $priority=0, $tags=[], $delay="0", $retries="5", $depends=[]){
        $useJID = empty($jid) ? Qless::guidv4() : $jid;
        $jsonData = json_encode($data,JSON_UNESCAPED_SLASHES);
        $jsonTags = json_encode($tags,JSON_UNESCAPED_SLASHES);
        $jsonDepends = json_encode($depends,JSON_UNESCAPED_SLASHES);
        return $this->client->put($worker,
            $this->name,
            $useJID,
            $klass,
            $jsonData,
            $delay,
            'priority', $priority,
            'tags', $jsonTags,
            'retries', $retries,
            'depends', $jsonDepends
        );
    }

    /**
     * Get the next job on this queue.
     * @param $worker - worker name poping the job.
     *
     * @return null|Job
     */
    public function pop($worker){
        $results = $this->client->pop(
            $this->name,
            $worker,
            1
        );

        $jobs = json_decode($results,true);

        $returnJob = null;
        if (!empty($jobs)){
            $job = $jobs[0];
            $returnJob = new Job($this->client, $job['jid'], $job['worker'], $job['klass'], $job['queue'], $job['state'], $job['data']);
        }

        return $returnJob;
    }

    /**
     * Get the length of the queue.
     */
    public function length(){
        $result = $this->client->length($this->name);
        //self.client('length', self.name)
    }

    /**
     * Get the stats on the queue.
     */
    public function stats(){
        $result = $this->client->stats($this->name, time());
        //self.client('stats', self.name, date or repr(time.time())))
    }
} 