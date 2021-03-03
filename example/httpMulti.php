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
    ],
    [
        'url'       => 'http://localhost/index3.php',
        'data'      => [],
        'method'    => 'get',
        'timeout'   => 2,
        'header'    => [],
    ],
    [
        'url'       => 'http://localhost/index2.php',
        'data'      => [],
        'method'    => 'delete',
        'timeout'   => 2,
        'header'    => [],
        'callback'  => function () {
            return 123666;
        }
    ],
    [
        'url'       => 'http://localhost/index.php',
        'data'      => [],
        'method'    => 'put',
        'timeout'   => 2,
        'header'    => [],
    ],
    [
        'url'       => 'http://localhost/index5.php',
        'data'      => [],
        'method'    => 'get',
        'timeout'   => 2,
        'header'    => [],
    ],
    [
        'url'       => 'http://localhost/index4.php',
        'data'      => [],
        'method'    => 'get',
        'timeout'   => 2,
        'header'    => [],
    ],
    [
        'url'       => 'http://localhost/index6.php',
        'data'      => [],
        'method'    => 'get',
        'timeout'   => 2,
        'header'    => [],
    ],
    [
        'url'       => 'http://localhost/index7.php',
        'data'      => [],
        'method'    => 'get',
        'timeout'   => 2,
        'header'    => [],
    ],
];

$result = HttpMulti::instance()->sendMultiQuery($query);
if (empty($result['error'])) {
    // 不存在失败的请求，则全部请求成功
    debug($result['success']);
} else {
    debug($result['error']);
}
