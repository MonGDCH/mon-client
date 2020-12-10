<?php

namespace mon\client;

use mon\client\hook\HttpHook;
use mon\client\exception\HttpException;

/**
 * HTTP请求类
 * 单个HTTP请求发起，IO阻塞
 * 
 * @author Mon <985558837@qq.com>
 * @version 2.0.0   支持GET、POST、PUT、DELETE请求类型
 * @version 3.0.0   支持设置请求头
 */
class Http
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
     * @return Http
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
     * HTTP以URL的形式发送请求
     *
     * @param   string  $url     请求地址
     * @param   array   $data    传递数据
     * @param   string  $type    请求类型
     * @param   boolean $toJson  解析json返回数组
     * @param   integer $timeOut 请求超时时间
     * @param   array   $header  请求头
     * @return  mixed 结果集
     */
    public function sendUrl($url, array $data = [], $type = 'GET', $toJson = false, $timeOut = 2, array $header = [])
    {
        $method = strtoupper($type);
        $queryData = $data;
        // get请求
        if (count($data) > 0 && $method == 'GET') {
            $uri = $this->arrToUri($data);
            $url = $url . $uri;
            $queryData = [];
        }

        return $this->sendRquest($url, $queryData, $method, $toJson, $timeOut, $header);
    }

    /**
     * CMD类型访问,相对性能较高
     *
     * @param   string  $serverName 配置文件中对应节点名
     * @param   array   $data       传输数据
     * @param   string  $type       请求类型
     * @param   boolean $toJson     是否返回JSON数据
     * @param   integer $timeOut    请求超时时间
     * @param   array   $header     请求头
     * @param   boolean $cache      是否读取缓存数据
     * @param   integer $cacheTime  缓存数据有效时间
     * @return  mixed   结果集
     */
    public function sendCMD($serverName, $data = [], $type = "GET", $toJson = false, $timeOut = 2, array $header = [], $cache = false, $cacheTime = 60)
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
        // 发起请求
        $result = $this->sendUrl($server['url'], $data, $type, $toJson, $timeOut, $header);
        // 缓存数据
        $this->requestCache[$serverName]['cache'] = $result;
        $this->requestCache[$serverName]['time'] = time();

        return $result;
    }

    /**
     * 数组转换成uri
     *
     * @param array $data 一维数组
     * @return string uri
     */
    public function arrToUri($data)
    {
        $ds = "&";
        $result = "";
        if (count($data) < 1) {
            $errorMsg = "[" . __METHOD__ . "]未能成功转换数组为URI";
            return $this->errorQuit($errorMsg);
        }
        foreach ($data as $key => $value) {
            $result = $result . $ds . trim($key) . "=" . trim($value);
        }

        return "?" . $result;
    }

    /**
     * 获取IP地址端口
     *
     * @param string $serverName 配置文件对应服务名
     * @return array
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
            "url"   => "http://{$ip}:{$port}/",
            "cache" => '',
            "time"  => time(),
        ];

        return $this->requestCache[$serverName];
    }

    /**
     * 设置CMD配置
     *
     * @param array $config 配置信息
     * @return array 配置信息
     */
    public function setConfig(array $config)
    {
        $this->config = array_merge($this->config, $config);

        return $this->config;
    }

    /**
     * 获取配置文件配置信息
     *
     * @return array 配置信息
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
     * @throws HttpException HTTP异常
     * @return void
     */
    protected function errorQuit($msg = "")
    {
        $msg = !empty($msg) ? $msg : "HTTP请求异常";
        // 抛出错误
        throw new HttpException($msg);
    }

    /**
     * 执行CURL请求
     *
     * @param  string  $url     请求的URL
     * @param  array   $data    请求的数据
     * @param  string  $type    请求方式
     * @param  boolean $toJson  是否解析json数据
     * @param  integer $timeOut 超时时间
     * @param  array   $header  请求头
     * @return mixed 结果集
     */
    protected function sendRquest($url, $data = [], $type = 'GET', $toJson = false, $timeOut = 2, array $header = [])
    {
        // 判断是否为https请求
        $ssl = strtolower(substr($url, 0, 8)) == "https://" ? true : false;
        $ch = curl_init();
        // 设置请求URL
        curl_setopt($ch, CURLOPT_URL, $url);

        // 判断请求类型
        switch ($type) {
            case "GET":
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                break;
            case "POST":
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case "PUT":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                break;
            case "DELETE":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            default:
                return $this->errorQuit('[' . __METHOD__ . ']不支持的请求类型(' . $type . ')');
        }
        // 判断是否需要传递数据
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        // 设置超时时间
        if ($timeOut > 0) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);
        }
        // 设置内容以文本形式返回，而不直接返回
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 防止无限重定向
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 6);
        // 设置user-agent
        $userAgent = (isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36";
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        // 设置请求头
        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        if ($ssl) {
            // 跳过证书检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // 从证书中检查SSL加密算法是否存在
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        // 执行请求前钩子
        HttpHook::listen('send_befor', ['tag' => 'send_befor', 'url' => $url, 'data' => $data, 'type' => $type, 'toJson' => $toJson, 'timeout' => $timeOut]);

        // 发起请求
        $html = curl_exec($ch);

        // 执行请求后钩子
        HttpHook::listen('send_after', ['tag' => 'send_after', 'url'  => $url, 'type' => $type, 'response' => $html]);

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($html === false || $status != 200) {
            // 执行请求失败钩子
            HttpHook::listen('send_faild', ['tag' => 'send_faild', 'url' => $url, 'type' => $type, 'status' => $status, 'error' => curl_error($ch), 'curl' => $ch]);
        }

        // 关于请求句柄
        curl_close($ch);
        $result = ($toJson) ? json_decode($html, true) : $html;

        // 执行返回结果集钩子
        HttpHook::listen('result_return', ['tag' => 'result_return', 'response' => $html, 'result' => $result]);

        return $result;
    }
}
