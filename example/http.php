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
        var_dump('class', $res);
    }
}

// 注册钩子，匿名函数回调
HttpHook::add('send_befor', function ($res) {
    var_dump($res);
});

// 注册构造，类方法回调
HttpHook::add('send_after', Hook::class);

// 其他钩子
HttpHook::add('send_faild', Hook::class);
HttpHook::add('result_return', Hook::class);

$res = Http::instance()->sendUrl('http://domain.com/admin/passport/login', ['a' => '123']);

var_dump($res);
