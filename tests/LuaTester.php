<?php

namespace Qless\Tests;

class LuaTester extends \Qless\Lua {

    public $time = 0;

    public function run($command, $args) {
        if (empty($this->sha)) {
            $this->reload();
        }
        $luaArgs  = [$command, $this->time];
        $argArray = array_merge($luaArgs, $args);
        $result = $this->redisCli->evalSha($this->sha, $argArray);
        $error  = $this->redisCli->getLastError();
        if ($error) {
            $this->handleError($error);
            return null;
        }

        return $result;
    }

}
