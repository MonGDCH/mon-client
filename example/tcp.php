<?php

use mon\client\Tcp;

require __DIR__ . '/../vendor/autoload.php';


$res = Tcp::instance()->sendTCP('127.0.0.1', 8888, json_encode(['cmd' => 1234, 'data' => 'test']));

var_dump($res);