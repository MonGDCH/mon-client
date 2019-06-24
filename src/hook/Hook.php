<?php
namespace mon\client\hook;

use Closure;
use mon\factory\Container;

/**
 * 业务钩子基类
 */
class Hook
{
    /**
     * 钩子列表
     *
     * @var array
     */
    protected static $tags = [];

    /**
     * 绑定一个钩子
     *
     * @param [type] $tag     钩子名称
     * @param [type] $callbak 钩子回调
     */
    public static function add($tag, $callbak)
    {
        // 只能绑定已定义的钩子
        if (isset(static::$tags[$tag])) {
            static::$tags[$tag][] = $callbak;
        }
    }

    /**
     * 获取钩子信息
     *
     * @param  string $tag 钩子名称
     * @return [type]      [description]
     */
    public static function get($tag = '')
    {
        if (empty($tag)) {
            //获取全部的插件信息
            return static::$tags;
        } else {
            return array_key_exists($tag, static::$tags) ? static::$tags[$tag] : [];
        }
    }

    /**
     * 监听执行行为
     *
     * @param  [type] $tag     钩子名称
     * @param  [type] &$params 参数
     * @return [type]          [description]
     */
    public static function listen($tag, $params = null)
    {
        $tags = static::get($tag);
        $results = [];
        foreach ($tags as $k => $v) {
            $results[$k] = static::exec($v, $k, $params);
            if ($results[$k] === false) {
                // 如果返回false 则中断行为执行
                break;
            }
        }
        return $results;
    }

    /**
     * 执行一个行为
     *
     * @param  [type] $class   行为回调
     * @param  string $tag     钩子名称
     * @param  [type] &$params 参数
     * @return [type]          [description]
     */
    public static function exec($class, $tag = '', $params = null)
    {
        if ($class instanceof Closure) {
            // 匿名回调
            return call_user_func_array($class, [$params]);
        } elseif (is_string($class) && !empty($class)) {
            // 类方法回调
            return Container::instance()->invokeMethd([$class, 'handler'], [$params]);
        }
    }
}
