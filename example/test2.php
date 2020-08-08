<?php

use mon\client\HttpMulti;

require __DIR__ . '/../vendor/autoload.php';

$query = [
    [
        'url'       => 'http://localhost',
        'data'      => [],
        'method'    => 'post',
        'timeout'   => 2,
        'header'    => [],
        'callback'  => null,
    ],
    [
        'url'       => 'http://localhost/index3.php',
        'data'      => [],
        'method'    => 'get',
        'timeout'   => 2,
        'header'    => [],
        'callback'  => null,
    ],
    [
        'url'       => 'http://localhost/index2.php',
        'data'      => [],
        'method'    => 'delete',
        'timeout'   => 2,
        'header'    => [],
        'callback'  => null,
    ],
    [
        'url'       => 'http://localhost',
        'data'      => [],
        'method'    => 'put',
        'timeout'   => 2,
        'header'    => [],
        'callback'  => null,
    ]
];

$result = HttpMulti::instance()->sendMultiQuery($query);

var_dump($result);