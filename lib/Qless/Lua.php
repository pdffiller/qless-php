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
    private $script;

    public function __construct($redis){
        $this->redisCli = $redis['redis'];
        $this->redisHost = $redis['host'];
        $this->redisPort = $redis['port'];
    }

    private function reload(){
        //$this->redisCli->script('flush');
        // data = pkgutil.get_data('qless', 'qless-core/qless.lua')
        $this->script = file_get_contents('qless-core/qless.lua', true);
        //$this->redisCli->connect($this->redisHost,$this->redisPort);
        //$this->sha = $this->redisCli->script('load', $script);

        //$this->redisCli->close();
    }

    public function run($command, $args){
        if (empty($this->script)){
            $this->reload();
        }
        $luaArgs = [$command, (string)(time())];
//        foreach ($args as $arg){
//            $luaArgs[] = $arg;
//        }
        //TODO:  Look at how this is run, redis has a script cache.  Is there a way I can only load it once and
        // detect it is in the redis cache?
        $argArray = array_merge($luaArgs, $args);
        $this->redisCli->connect($this->redisHost,$this->redisPort);
        $result = $this->redisCli->eval($this->script, $argArray);
        $checkError = $this->redisCli->getLastError();
        $this->redisCli->close();
        return $result;
    }

} 