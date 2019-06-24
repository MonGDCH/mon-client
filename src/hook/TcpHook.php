<?php
namespace mon\client\hook;

use mon\client\hook\Hook;

/**
 * TCP业务钩子
 */
class TcpHook extends Hook
{
    /**
     * 钩子列表
     *
     * @var array
     */
    protected static $tags = [
        // 创建Socket失败
        'create_faild'    => [],
        // 链接Socket失败
        'connect_faild'    => [],
        // 发送CMD指令失败
        'sned_faild'    => [],
        // 请求完成，获取结果集
        'send_after' => [],
        // 返回响应数据
        'result_return' => [],
    ];
}
