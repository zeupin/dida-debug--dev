<?php
/**
 * Dida Framework  -- A Rapid Development Framework
 * Copyright (c) Zeupin LLC. (http://zeupin.com)
 *
 * Licensed under The MIT License.
 * Redistributions of files MUST retain the above copyright notice.
 */

namespace Dida\Debug;

use \ReflectionObject;

/**
 * Debug 类
 */
class Debug
{
    /**
     * Version
     */
    const VERSION = '20171124';

    /**
     * 需要显示何种对象属性。
     * @var int
     */
    protected static $filter_prop_type = \ReflectionProperty::IS_PUBLIC;

    /**
     * 需要忽略的属性名。
     * [
     *     "classname" => [属性名1,属性名2, ...]
     * ]
     * @var array
     */
    protected static $filter_prop_ignores = [];

    /**
     * @var array
     */
    protected $objects = [];

    /**
     * @var int
     */
    protected $objID = 0;


    /**
     * 显示一个需要跟踪的变量，然后停止运行
     *
     * 如果是想不显示变量就直接停止的话，建议用PHP自带的die()或者exit()。
     * 本类主要目的是Debug用途，函数设计时，重点考虑的是Debug时的方便。
     */
    public static function halt($var, $varname = null)
    {
        self::variable($var, $varname);
        exit();
    }


    /**
     * 显示一个需要跟踪的变量
     */
    public static function variable($var, $varname = null)
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<pre>' . htmlspecialchars(self::varExport($var, $varname)) . '</pre>';
    }


    /**
     * 对象输出时，只输出选择类型的属性。
     *
     * @param boolean $public
     * @param boolean $protected
     * @param boolean $private
     */
    public static function filterPropType($public = true, $protected = false, $private = false)
    {
        $flag = 0;
        if ($public) $flag = $flag | \ReflectionProperty::IS_PUBLIC;
        if ($protected) $flag = $flag | \ReflectionProperty::IS_PROTECTED;
        if ($private) $flag = $flag | \ReflectionProperty::IS_PRIVATE;

        self::$filter_prop_type = $flag;
    }


    /**
     * @param type $ignores
     */
    public static function filterPropNames(array $ignores)
    {
        self::$filter_prop_ignores = $ignores;
    }


    /**
     * 导出变量
     */
    public static function varDump()
    {
        $debug = new Debug();

        $result = [];
        $num = func_num_args();
        for ($i = 0; $i < $num; $i++) {
            $var = func_get_arg($i);
            $no = $i + 1;
            $result[] = "No.{$no} = " . $debug->formatVar($var);
        }

        return "\n" . implode("\n", $result) . "\n";
    }


    /**
     * 输出或返回一个变量的字符串表示
     *
     * @param mixed $var 变量
     * @param string $varname 变量名
     */
    public static function varExport($var, $varname = null)
    {
        $debug = new Debug();

        // 如果不设置变量名，则等效于self::varDump()
        if (!is_string($varname) || $varname === '') {
            return $debug->formatVar($var);
        }

        // 变量名 = 变量值;
        $begin = $varname . ' = ';
        $leading = strlen($begin);
        $v = $debug->formatVar($var, $leading);
        $end = ';' . PHP_EOL;

        return $begin . $v . $end;
    }


    /**
     * 把一个变量的值，用可读性良好的格式进行输出
     *
     * @return string
     */
    protected function formatVar($var, $leading = 0)
    {
        // 为 null
        if (is_null($var)) {
            return 'null';
        }

        // 为数组
        if (is_array($var)) {
            return $this->formatArray($var, $leading);
        }

        // 为对象
        if (is_object($var)) {
            return $this->formatObject($var, $leading);
        }

        // 其它类型
        return var_export($var, true);
    }


    /**
     * 把一个数组的值，用可读性良好的格式进行输出
     *
     * @param array $array
     * @param int $leading 前导空格的数量
     * @return string
     */
    protected function formatArray($array, $leading = 0)
    {
        // 如果是空数组，直接返回[]
        if (empty($array)) {
            return '[]';
        }

        // 前导空格
        $leadingspaces = str_repeat(' ', $leading);

        // 找出名称最长的key
        $maxlen = 0;
        $keys = array_keys($array);
        $is_string_key = false;
        foreach ($keys as $key) {
            if (is_string($key)) {
                $is_string_key = true;
            }
            $len = mb_strwidth($key);
            if ($len > $maxlen) {
                $maxlen = $len;
            }
        }
        if ($is_string_key) {
            $maxlen = $maxlen + 2;
        }

        // 生成数组定义个每一行
        $s = [];
        $s[] = '['; // 第一行无需前导空格
        foreach ($array as $key => $value) {
            $key = (is_string($key)) ? "'$key'" : $key;
            $value = $this->formatVar($value, $leading + $maxlen + 8);
            $s[] = sprintf("%s    %-{$maxlen}s => %s,", $leadingspaces, $key, $value);
        }
        $s[] = $leadingspaces . ']';    // 最后一行

        return implode(PHP_EOL, $s);
    }


    protected function getNewObjID()
    {
        $this->objID ++;
        return $this->objID;
    }


    /**
     * 把一个对象的值，用可读性良好的格式输出出来。
     *
     * @param mixed $obj
     * @param int $leading
     * @return string
     */
    protected function formatObject($obj, $leading = 0)
    {
        $r = new \ReflectionObject($obj);
        $className = $r->getName();

        // 检查本对象是否已经显示过
        if (isset($this->objects[$className])) {
            // 如果已经显示过，不要重复输出
            foreach ($this->objects[$className] as $uuid => $o) {
                if ($o === $obj) {
                    return "($className #$uuid) {...}";
                }
            }
            // 如果还没有显示过，创建一条新纪录
            $uuid = $this->getNewObjID();
            $this->objects[$className][$uuid] = $obj;
        } else {
            // 如果还没有显示过，创建一条新纪录
            $this->objects[$className] = [];
            $uuid = $this->getNewObjID();
            $this->objects[$className][$uuid] = $obj;
        }

        // 准备前导空格
        $leadingspace = str_repeat(' ', $leading);

        // 输出
        $output = [];
        $output[] = "($className #$uuid)";
        $output[] = $leadingspace . "{";

        // 按照类型要求，筛选出属性列表。
        // self::$filter_prop_type 参数可以用 filterPropType()生成。
        $properties = $r->getProperties(self::$filter_prop_type);

        // 逐一显示属性
        foreach ($properties as $property) {
            // 属性名
            $propName = $property->getName();

            // 检查是否需要忽略
            if ($this->ignored($className, $propName)) {
                continue;
            }

            // static还是普通
            $propStatic = ($property->isStatic()) ? '::' : '->';

            // 访问性是 public/protected/private
            if ($property->isPublic()) {
                $propAccess = '';
            } elseif ($property->isProtected()) {
                $propAccess = '*';
            } elseif ($property->isPrivate()) {
                $propAccess = '!';
            }
            $propStr = "    {$propStatic}{$propName}{$propAccess} = ";

            // 属性值
            $property->setAccessible(true);
            $propValue = $this->formatVar($property->getValue($obj), $leading + strlen($propStr));

            // 记录
            $output[] = "{$leadingspace}{$propStr}{$propValue}";
        }

        $output[] = $leadingspace . "}";

        return implode("\n", $output) . "\n";
    }


    protected function ignored($class, $propName)
    {
        if (isset(self::$filter_prop_ignores[$class])) {
            return in_array($propName, self::$filter_prop_ignores[$class]);
        } else {
            return false;
        }
    }
}
