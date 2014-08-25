<?php

$config = require(__DIR__ . '/console.php');

unset($config['components']['cache']);
unset($config['components']['log']);

return $config;