<?php
namespace mon\client;

use mon\client\hook\UdpHook;
use mon\client\exception\UdpException;

/**
 * UDP请求类
 */
class Udp
{
    /**
     * 配置信息
     *
     * @var array
     */
    private static $config = [];

    /**
     * 缓存已请求的server配置
     *
     * @var array
     */
    private static $requestCache = [];

    /**
     * 发送TCP请求
     *
     * @param string  $ip       IP
     * @param int     $port     端口
     * @param string  $cmd      请求命令套接字
     * @param integer $timeOut  超时时间
     * @param boolean $toJson   是否转换JSON数组为数组
     * @param boolean $close    是否关闭链接
     * @return void
     */
    public static function sendTCP($ip, $port, $cmd, $timeOut = 2, $toJson = false, $close = true)
    {
        return self::send($ip, $port, $cmd, $timeOut, $toJson, $close);
    }

    /**
     * CMD类型访问,相对性能较高
     *
     * @param   string  $serverName 配置文件中对应节点名
     * @param   string  $cmd        请求命令套接字
     * @param   int     $timeOut    请求超时时间
     * @param   boolean $toJson     是否转换JSON数组为数组
     * @param   boolean $close      是否关闭链接
     * @param   bool    $cache      是否读取缓存数据
     * @param   int     $cacheTime  缓存数据有效时间
     * @return  $result;
     */
    public static function excuteCMD($serverName, $cmd, $timeOut = 2, $toJson = false, $close = true, $cache = false, $cacheTime = 60)
    {
        $server = self::getServer($serverName);
        if (empty($server)) {
            $errorMsg = "未能成功创建[ " . $serverName . " ]访问节点";
            self::errorQuit($errorMsg);
        }
        // 判断是否允许获取缓存数据
        if ($cache && !empty($server['chche']) && (time() - $server['time']) <= $cacheTime) {
            return $server['cache'];
        }
        // CMD请求
        $result = self::send($server['ip'], $server['port'], $cmd, $timeOut, $toJson, $close);
        // 缓存数据
        self::$requestCache[$serverName]['cache'] = $result;
        self::$requestCache[$serverName]['time'] = time();

        return $result;
    }

    /**
     * 设置CMD配置
     *
     * @param array $config 配置信息
     */
    public static function setConfig(array $config)
    {
        self::$config = array_merge(self::$config, $config);

        return self::$config;
    }

    /**
     * 获取IP地址端口
     *
     * @param string $serverName 配置文件对应服务名
     * @return array
     */
    public static function getServer($serverName)
    {
        // 判断是否存在请求缓存
        if (!empty(self::$requestCache[$serverName])) {
            return self::$requestCache[$serverName];
        }
        if (empty(self::$config)) {
            self::getConfig();
        }
        // 判断配置文件中是否存在对应节点
        if (empty(self::$config[$serverName])) {
            $errorMsg = "配置文件未设置对应节点";
            self::errorQuit($errorMsg);
        }
        // 创建请求地址实例，缓存实例
        $num  = self::$config[$serverName]['num'];
        $port = self::$config[$serverName]['port'];
        $rand = ($num > 0) ? mt_rand(0, $num - 1) : 0;
        $ip   = self::$config[$serverName]['ip' . $rand];
        // 缓存
        self::$requestCache[$serverName] = [
            "ip"    => $ip,
            "port"  => $port,
            "cache" => '',
            "time"  => time(),
        ];

        return self::$requestCache[$serverName];
    }

    /**
     * 获取配置文件配置信息
     *
     * @return [type] [description]
     */
    public static function getConfig()
    {
        if (empty(self::$config)) {
            $errorMsg = "配置信息不能为空";
            self::errorQuit($errorMsg);
        }

        return self::$config;
    }

    /**
     * 错误返回,抛出错误
     *
     * @param string $msg 错误提示信息
     * @return Error
     */
    private static function errorQuit($msg = "")
    {
        $msg = !empty($msg) ? $msg : "HTTP请求异常";
        // 抛出错误
        throw new UdpException($msg);
    }

    /**
     * 发送UDP请求
     *
     * @param string  $ip       IP
     * @param int     $port     端口
     * @param string  $cmd      请求命令套接字
     * @param integer $timeOut  超时时间
     * @param boolean $toJson   是否转换JSON数组为数组
     * @param boolean $close    是否关闭链接
     * @return void
     */
    protected static function send($ip, $port, $cmd, $timeOut = 2, $toJson = false, $close = true)
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            // 执行创建Socket失败钩子
            UdpHook::listen('create_faild', ['error' => socket_strerror(socket_last_error()), 'cmd' => $cmd, 'ip' => $ip, 'port' => $port]);
            return self::errorQuit('创建Socket失败');
        }
        $timeouter = ['sec' => $timeOut, 'usec' => 0];
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, $timeouter);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, $timeouter);
        if (socket_connect($socket, $ip, $port) == false) {
            // 执行链接Socket失败钩子
            UdpHook::listen('connect_faild', ['error' => socket_strerror(socket_last_error()), 'cmd' => $cmd, 'ip' => $ip, 'port' => $port]);
            return self::errorQuit('链接Socket失败');
        }
        $send_len = strlen($cmd);
        $sent = socket_write($socket, $cmd, $send_len);
        if ($sent != $send_len) {
            // 执行发送CMD指令失败钩子
            UdpHook::listen('sned_faild', ['error' => socket_strerror(socket_last_error()), 'cmd' => $cmd, 'ip' => $ip, 'port' => $port]);
            return self::errorQuit('发送CMD指令失败');;
        }
        // 读取返回数据
        $data = socket_read($socket, 1024);
        // 执行结束，执行读取数据钩子
        UdpHook::listen('send_after', ['response' => $data, 'cmd' => $cmd, 'ip' => $ip, 'port' => $port]);

        // 是否转换Json格式
        $result = $toJson ? json_decode($data, true) : $data;
        // 是否关闭链接
        if ($close) {
            socket_close($socket);
        }

        // 执行返回结果集钩子
        HttpHook::listen('result_return', ['response' => $data, 'result' => $result]);
        return $result;
    }
}
