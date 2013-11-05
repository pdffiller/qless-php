<?php
/**
 * Created by PhpStorm.
 * User: paul
 * Date: 10/30/13
 * Time: 3:17 PM
 */

namespace Qless;


/**
 * Class Lua
 * wrapper to load and execute lua script for qless-core.
 *
 * @package Qless
 */
class Lua {

    private $redisCli;
    private $redisHost;
    private $redisPort;
    private $sha = null;

    public function __construct($redis){
        $this->redisCli = $redis['redis'];
        $this->redisHost = $redis['host'];
        $this->redisPort = $redis['port'];
    }

    private function reload(){
        $script = file_get_contents('qless-core/qless.lua', true);
        $this->sha = sha1($script);
        $this->redisCli->connect($this->redisHost,$this->redisPort);
        $res = $this->redisCli->script('exists', $this->sha);
        if ($res[0] !== 1) {
            $this->sha = $this->redisCli->script('load', $script);
        }
        $this->redisCli->close();
    }

    public function run($command, $args){
        if (empty($this->sha)){
            $this->reload();
        }
        $luaArgs = [$command, (string)(time())];
        $argArray = array_merge($luaArgs, $args);
        $this->redisCli->connect($this->redisHost,$this->redisPort);
        $result = $this->redisCli->evalSha($this->sha, $argArray);
        $checkError = $this->redisCli->getLastError();
        $this->redisCli->close();
        return $result;
    }

} 