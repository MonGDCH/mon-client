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
     * 单例实体
     *
     * @var null
     */
    protected static $instance = null;

    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [];

    /**
     * 缓存已请求的server配置
     *
     * @var array
     */
    protected $requestCache = [];

    /**
     * 单例实现
     *
     * @param array $config 配置信息
     * @return Udp
     */
    public static function instance(array $config = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($config);
        }

        return self::$instance;
    }

    /**
     * 私有化构造方法
     *
     * @param array $config 配置信息
     */
    protected function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 发送TCP请求
     *
     * @param string  $ip       IP
     * @param integer $port     端口
     * @param string  $cmd      请求命令套接字
     * @param integer $timeOut  超时时间
     * @param boolean $toJson   是否转换JSON数组为数组
     * @param boolean $close    是否关闭链接
     * @return mixed 结果集
     */
    public function sendTCP($ip, $port, $cmd, $timeOut = 2, $toJson = false, $close = true)
    {
        return $this->send($ip, $port, $cmd, $timeOut, $toJson, $close);
    }

    /**
     * CMD类型访问,相对性能较高
     *
     * @param   string  $serverName 配置文件中对应节点名
     * @param   string  $cmd        请求命令套接字
     * @param   integer $timeOut    请求超时时间
     * @param   boolean $toJson     是否转换JSON数组为数组
     * @param   boolean $close      是否关闭链接
     * @param   boolean $cache      是否读取缓存数据
     * @param   integer $cacheTime  缓存数据有效时间
     * @return  mixed 结果集
     */
    public function sendCMD($serverName, $cmd, $timeOut = 2, $toJson = false, $close = true, $cache = false, $cacheTime = 60)
    {
        $server = $this->getServer($serverName);
        if (empty($server)) {
            $errorMsg = "未能成功创建[ " . $serverName . " ]访问节点";
            return $this->errorQuit($errorMsg);
        }
        // 判断是否允许获取缓存数据
        if ($cache && !empty($server['chche']) && (time() - $server['time']) <= $cacheTime) {
            return $server['cache'];
        }
        // CMD请求
        $result = $this->send($server['ip'], $server['port'], $cmd, $timeOut, $toJson, $close);
        // 缓存数据
        $this->requestCache[$serverName]['cache'] = $result;
        $this->requestCache[$serverName]['time'] = time();

        return $result;
    }

    /**
     * 设置CMD配置
     *
     * @param array $config 配置信息
     * @return array CMD配置信息
     */
    public function setConfig(array $config)
    {
        $this->config = array_merge($this->config, $config);

        return $this->config;
    }

    /**
     * 获取IP地址端口
     *
     * @param string $serverName 配置文件对应服务名
     * @return array 服务信息
     */
    public function getServer($serverName)
    {
        // 判断是否存在请求缓存
        if (!empty($this->requestCache[$serverName])) {
            return $this->requestCache[$serverName];
        }
        if (empty($this->config)) {
            $this->getConfig();
        }
        // 判断配置文件中是否存在对应节点
        if (empty($this->config[$serverName])) {
            $errorMsg = "配置文件未设置对应节点";
            return $this->errorQuit($errorMsg);
        }
        // 创建请求地址实例，缓存实例
        $num  = $this->config[$serverName]['num'];
        $port = $this->config[$serverName]['port'];
        $rand = ($num > 0) ? mt_rand(0, $num - 1) : 0;
        $ip   = $this->config[$serverName]['ip' . $rand];
        // 缓存
        $this->requestCache[$serverName] = [
            "ip"    => $ip,
            "port"  => $port,
            "cache" => '',
            "time"  => time(),
        ];

        return $this->requestCache[$serverName];
    }

    /**
     * 获取配置文件配置信息
     *
     * @return array
     */
    public function getConfig()
    {
        if (empty($this->config)) {
            $errorMsg = "配置信息不能为空";
            return $this->errorQuit($errorMsg);
        }

        return $this->config;
    }

    /**
     * 错误返回,抛出错误
     *
     * @param string $msg 错误提示信息
     * @throws UdpException UDP异常
     * @return void
     */
    protected function errorQuit($msg = "")
    {
        $msg = !empty($msg) ? $msg : "UDP请求异常";
        // 抛出错误
        throw new UdpException($msg);
    }

    /**
     * 发送UDP请求
     *
     * @param string  $ip       IP
     * @param integer $port     端口
     * @param string  $cmd      请求命令套接字
     * @param integer $timeOut  超时时间
     * @param boolean $toJson   是否转换JSON数组为数组
     * @param boolean $close    是否关闭链接
     * @return mixed 结果集
     */
    protected function send($ip, $port, $cmd, $timeOut = 2, $toJson = false, $close = true)
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            // 执行创建Socket失败钩子
            UdpHook::listen('create_faild', ['tag' => 'create_faild', 'error' => socket_strerror(socket_last_error()), 'cmd' => $cmd, 'ip' => $ip, 'port' => $port]);
            return $this->errorQuit('创建Socket失败');
        }
        $timeouter = ['sec' => $timeOut, 'usec' => 0];
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, $timeouter);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, $timeouter);
        if (socket_connect($socket, $ip, $port) == false) {
            // 执行链接Socket失败钩子
            UdpHook::listen('connect_faild', ['tag' => 'connect_faild', 'error' => socket_strerror(socket_last_error()), 'cmd' => $cmd, 'ip' => $ip, 'port' => $port]);
            return $this->errorQuit('链接Socket失败');
        }
        $send_len = strlen($cmd);
        $sent = socket_write($socket, $cmd, $send_len);
        if ($sent != $send_len) {
            // 执行发送CMD指令失败钩子
            UdpHook::listen('sned_faild', ['tag' => 'sned_faild', 'error' => socket_strerror(socket_last_error()), 'cmd' => $cmd, 'ip' => $ip, 'port' => $port]);
            return $this->errorQuit('发送CMD指令失败');;
        }
        // 读取返回数据
        $data = socket_read($socket, 1024);
        // 执行结束，执行读取数据钩子
        UdpHook::listen('send_after', ['tag' => 'send_after', 'response' => $data, 'cmd' => $cmd, 'ip' => $ip, 'port' => $port]);

        // 是否转换Json格式
        $result = $toJson ? json_decode($data, true) : $data;
        // 是否关闭链接
        if ($close) {
            socket_close($socket);
        }

        // 执行返回结果集钩子
        UdpHook::listen('result_return', ['tag' => 'result_return', 'response' => $data, 'result' => $result]);
        return $result;
    }
}
