<?php
namespace mon\client\hook;

use mon\client\hook\Hook;

/**
 * HTTP业务钩子
 */
class HttpHook extends Hook
{
    /**
     * 钩子列表
     *
     * @var array
     */
    protected static $tags = [
        // 发送请求前
        'send_befor'    => [],
        // 发送请求后，获取结果集
        'send_after'    => [],
        // 请求失败
        'send_faild'    => [],
        // 返回响应数据
        'result_return' => [],
    ];
}
