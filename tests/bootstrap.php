<?php

require_once __DIR__ . '/../vendor/autoload.php';

defined('REDIS_HOST')    || define('REDIS_HOST', (string) (getenv('REDIS_HOST') ?: '127.0.0.1'));
defined('REDIS_PORT')    || define('REDIS_PORT', (int) (getenv('REDIS_PORT') ?: 6379));
