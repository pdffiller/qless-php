<?php
/**
 * Created by PhpStorm.
 * User: paul
 * Date: 10/31/13
 * Time: 2:10 PM
 */

namespace Qless;


class Job {

    private $jid;
    private $data;
    private $client;
    private $queue_name;
    private $klass_name;
    private $state;
    private $worker;
    private $instance;

    public function __construct($client, $jid, $worker, $klass_name, $queue_name, $state, $data){
        $this->jid = $jid;
        $this->client = $client;
        $this->klass_name = $klass_name;
        $this->queue_name = $queue_name;
        $this->state = $state;
        $this->data = json_decode($data,true);
        $this->worker = $worker;
    }

    public function complete(){
        //$jsonDepends = json_encode($depends,JSON_UNESCAPED_SLASHES);
        $jsonData = json_encode($this->data,JSON_UNESCAPED_SLASHES);
        $return = $this->client->complete(
            $this->jid,
            $this->worker,
            $this->queue_name,
            $jsonData
        );

        return $return ? $return : false;

    }

    public function fail($group, $message){
        $jsonData = json_encode($this->data);
        $return =  $this->client->fail(
            $this->jid,
            $this->worker,
            $group,
            $message,
            $jsonData
        );
        if (!$return){
            $return = false;
        }
        return $return;
    }

    /**
     * Creates the instance to perform the job and calls the method on the Instance specified in the payload['performMethod'];
     * @return bool
     */
    public function perform()
    {

        try {
            $instance = $this->getInstance();
//            if(method_exists($instance, 'setUp')) {
//                $instance->setUp();
//            }

            $performMethod = $this->data['performMethod'];

            $instance->$performMethod($this);

//            if(method_exists($instance, 'tearDown')) {
//                $instance->tearDown();
//            }
        }
        catch(\Exception $e) {
            $this->fail("job exception",$e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Get the instance of the class specified on this job.  This instance will
     * be used to call the payload['performMethod']
     * @return mixed
     * @throws \Exception
     */
    public function getInstance()
    {
        if (!is_null($this->instance)) {
            return $this->instance;
        }

        if(!class_exists($this->klass_name)) {
            throw new \Exception(
                'Could not find job class ' . $this->klass_name . '.'
            );
        }

        if(!method_exists($this->klass_name, $this->data['performMethod'])) {
            throw new \Exception(
                'Job class ' . $this->klass_name . ' does not contain perform method ' . $this->data['performMethod']
            );
        }

        $this->instance = new $this->klass_name;
        //$this->instance->job = $this;
        //$this->instance->args = $this->getArguments();
        //$this->instance->queue = $this->queue;
        return $this->instance;
    }

} 