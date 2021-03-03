<?php

namespace mon\client;

use mon\util\Instance;
use mon\util\Container;
use mon\client\exception\HttpException;

/**
 * HTTP请求异步并发类
 * 支持多个HTTP请求并发请求，请求结束则执行异步回调
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.1.0   优化代码，增加业务钩子
 */
class HttpMulti
{
    use Instance;

    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 默认最大滚动窗口数
        'rolling'   => 5,
        // 默认请求类型
        'method'    => 'GET',
        // 默认超时时间
        'timeout'   => 2,
        // 默认请求头
        'header'    => [],
        // 默认user-agent
        'agent'     => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36',
    ];

    /**
     * 私有化构造方法
     *
     * @param array $config 配置信息
     */
    protected function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取配置信息
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
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
            $this->errorQuit($errorMsg);
        }
        foreach ($data as $key => $value) {
            $result = $result . $ds . trim($key) . "=" . trim($value);
        }

        return "?" . $result;
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
     * 生成curl句柄
     * 
     * @param array $queryList 请求列表
     * @return array 成功结果集与失败结果集
     */
    public function sendMultiQuery(array $queryList)
    {
        $result = [];
        $errors = [];
        $curls = [];
        $master = curl_multi_init();
        // 确保滚动窗口不大于网址数量
        $rolling = $this->config['rolling'];
        $rolling = (count($queryList) < $rolling) ? count($queryList) : $rolling;
        for ($i = 0; $i < $rolling; $i++) {
            $item = $queryList[$i];
            // 获取curl
            $ch = $this->getCh($item);
            // 写入批量请求
            curl_multi_add_handle($master, $ch);
            // 记录队列
            $key = (string)$ch;
            $curls[$key] = [
                'index' => $i,
                'data'  => $item,
            ];
        }
        // 发起请求
        do {
            while (($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);
            if ($execrun != CURLM_OK) {
                break;
            }

            while ($done = curl_multi_info_read($master)) {
                // 获取请求信息
                $info = curl_getinfo($done['handle']);
                // 请求成功
                if ($info['http_code'] == 200) {
                    // 获取返回内容
                    $output = curl_multi_getcontent($done['handle']);
                    // 请求成功，存在回调函数，执行回调函数
                    $key = (string) $done['handle'];
                    // debug($key);
                    if (isset($curls[$key]) && isset($curls[$key]['data']['callback']) && !empty($curls[$key]['data']['callback'])) {
                        $output = Container::instance()->invoke($curls[$key]['data']['callback'], [$output, $curls[$key], $done['handle']]);
                    }
                    $result[] = $output;
                } else {
                    // 请求失败，执行错误处理
                    $key = (string) $done['handle'];
                    $errors[] = [
                        'ch'    => $done['handle'],
                        'item'  => $curls[$key]
                    ];
                }

                // 发起新请求（在删除旧请求之前，请务必先执行此操作）, 当$i等于$urls数组大小时不用再增加了
                if ($i < count($queryList)) {
                    $ch = $this->getCh($queryList[$i++]);
                    curl_multi_add_handle($master, $ch);
                    // 记录队列
                    $key = (string)$ch;
                    $curls[$key] = [
                        'index' => $i,
                        'data'  => $item,
                    ];
                }
                // 执行下一个句柄
                curl_multi_remove_handle($master, $done['handle']);
            }
        } while ($running);

        return [
            'success'   => $result,
            'error'     => $errors
        ];
    }

    /**
     * 解析请求列表项，获取curl
     *
     * @param array $item 请求配置信息
     * @return resource cURL句柄
     */
    protected function getCh(array $item)
    {
        // 请求URL
        if (!isset($item['url']) || empty($item['url'])) {
            return $this->errorQuit('[' . __METHOD__ . ']请求列表必须存在url参数');
        }
        $url = $item['url'];
        // 请求方式，默认使用get请求
        $method = (isset($item['method']) && !empty($item['method'])) ? strtoupper($item['method']) : $this->config['method'];
        // 请求数据
        $data = [];
        if (isset($item['data']) && !empty($item['data'])) {
            $data = $item['data'];
            if ($method == 'GET') {
                $uri = $this->arrToUri($data);
                $url = $url . $uri;
                $data = [];
            }
        }
        // 超时时间，默认2s
        $timeOut = (isset($item['timeout']) && is_numeric($item['timeout'])) ? $item['timeout'] : $this->config['timeout'];
        // 请求头
        $header = (isset($item['header']) && !empty($item['header'])) ? $item['header'] : $this->config['header'];
        // 获取curl请求
        $ch = $this->getRequest($url, $data, $method, $timeOut, $header);

        return $ch;
    }

    /**
     * 生成CURL请求
     *
     * @param  string  $url     请求的URL
     * @param  array   $data    请求的数据
     * @param  string  $type    请求方式
     * @param  integer $timeOut 超时时间
     * @param  array   $header  请求头
     * @return resource cURL句柄
     */
    protected function getRequest($url, $data = [], $type = 'GET', $timeOut = 2, array $header = [])
    {
        // 判断是否为https请求
        $ssl = strtolower(substr($url, 0, 8)) == "https://" ? true : false;
        $ch = curl_init();
        // 设置请求URL
        curl_setopt($ch, CURLOPT_URL, $url);

        // 判断请求类型
        switch ($type) {
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
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
        $userAgent = (isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : $this->config['agent'];
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

        return $ch;
    }
}
