<?php
/**
 * Created by PhpStorm.
 * User: paul
 * Date: 10/30/13
 * Time: 1:25 PM
 */

namespace Qless;

include 'Lua.php';

use \Redis;


/**
 * Class Client
 * client to call lua scripts in qless-core for specific commands
 *
 * @package Qless
 */
class Client {

    private $redis = [];
    private $lua;

    public function __construct($host='localhost',$port=6379){
        $this->redis['redis'] = new Redis();
        $this->redis['host'] = $host;
        $this->redis['port'] = $port;

       $this->lua = new Lua($this->redis);
    }

    public function __call($command, $arguments){
        if ($this->lua){
            $result = $this->lua->run($command,$arguments);
            return $result;
        }
        throw new \Exception("lua script not found.");
    }

    public function getQueue($name){
        return new Queue($name,$this);
    }

} 