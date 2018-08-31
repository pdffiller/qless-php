<?php

// Here you can initialize variables that will be available to demo

defined('REDIS_HOST') || define('REDIS_HOST', getenv('REDIS_HOST') ?: '127.0.0.1');
defined('REDIS_PORT') || define('REDIS_PORT', getenv('REDIS_PORT') ?: 6379);
