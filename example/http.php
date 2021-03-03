<?php

use mon\client\Http;
use mon\client\hook\HttpHook;

require __DIR__ . '/../vendor/autoload.php';

/**
 * 类方法钩子回调
 */
class Hook
{
    public function handler($res)
    {
        // echo 'class';
        // debug($res);
    }
}

// 注册钩子，匿名函数回调
HttpHook::add('send_befor', function ($res) {
    // debug($res);
});

// 注册构造，类方法回调
HttpHook::add('send_after', Hook::class);

// 其他钩子
HttpHook::add('send_faild', Hook::class);
HttpHook::add('result_return', Hook::class);

// $result = Http::instance()->sendURL('http://domain.com/admin/passport/login', ['a' => '123']);
$result = Http::instance()->sendURL('http://localhost/index2.php', ['a' => '123'], 'put');

debug($result);
