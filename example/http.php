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
$result = Http::instance()->sendURL('http://localhost/index2.php', ['test' => '111'], 'patch');


function puturl($url, $data)
{
    $data = json_encode($data);
    $ch = curl_init(); //初始化CURL句柄 
    curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type:application/json'));
    // curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type:application/x-www-form-urlencoded;'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出 
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); //设置请求方式
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data); //设置提交的字符串
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
    // return json_decode($output, true);
}

// $result = puturl('http://localhost/index2.php', ['a' => 2]);

debug($result);
