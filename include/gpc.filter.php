<?php
/**
 * GPC 输入过滤(公共版)
 *
 * 对 $_GET / $_POST / $_COOKIE / $_REQUEST 做统一的安全字符过滤。
 * PHP 8 下已不存在 magic_quotes,本文件不再做任何 stripslashes/addslashes 处理,
 * 仅保留关键词过滤与 XSS 字符剥离。
 *
 * @package SeaCMS
 */

if (!defined('GPC_FILTER_LOADED')) {
    define('GPC_FILTER_LOADED', true);

    /**
     * 剥离输入字符串中的危险字符与注入关键词
     *
     * @param string $str 原始字符串
     * @return string|false 过滤后的字符串,空输入返回 false
     */
    function lib_replace_end_tag($str)
    {
        if (empty($str)) {
            return false;
        }

        // XSS 字符剥离
        $str = htmlspecialchars($str);
        $stripChars = array('/', '[', ']', '>', '<', '?', '&', '|', '(', ')',
            '{', '}', '%', '=', ',', ':', ';', '*', '@', '--', '//', '\\', '#');
        foreach ($stripChars as $char) {
            $str = str_ireplace($char, '', $str);
        }

        // SQL 注入关键词混淆剥离
        $stripWords = array(
            'if'                   => 'i',
            '0x'                   => '',
            '%00'                  => '',
            '%0b'                  => '',
            '%23'                  => '',
            '%26'                  => '',
            '%7c'                  => '',
            'hex'                  => 'he',
            'file_'                => 'fil',
            'updatexml'            => 'update',
            'extractvalue'         => 'extract',
            'union'                => 'unio',
            'benchmark'            => 'bench',
            'sleep'                => 'slee',
            'load_file'            => '',
            'outfile'              => 'out',
            'ascii'                => 'asc',
            'char'                 => 'cha',
            'chr'                  => 'ch',
            'substr'               => 'sub',
            'substring'            => 'sub',
            'script'               => 'scri',
            'frame'                => 'fra',
            'information_schema'   => 'infor',
            'exp'                  => 'ex',
            'geometrycollection'   => 'geomet',
            'polygon'              => 'poly',
            'multipoint'           => 'multi',
            'multilinestring'      => 'multi',
            'linestring'           => 'lines',
            'multipolygon'         => 'multi',
            'base64'               => 'bas',
            '/*'                   => '',
            '*/'                   => '',
            '*!'                   => '',
        );
        foreach ($stripWords as $word => $replacement) {
            $str = str_ireplace($word, $replacement, $str);
        }

        return $str;
    }

    /**
     * 递归过滤数组中的所有字符串值
     *
     * @param array $array 待过滤数组
     * @return array 过滤后的数组
     */
    function stripslashes_array($array)
    {
        foreach ($array as $key => $var) {
            if ($key != 'argc' && $key != 'argv'
                && (strtoupper($key) != $key || ''.intval($key) == "$key")) {
                if (is_string($var)) {
                    $array[$key] = lib_replace_end_tag($var);
                }
                if (is_array($var)) {
                    $array[$key] = stripslashes_array($var);
                }
            }
        }

        return $array;
    }

    // 对超全局数组统一过滤(注:PHP 8.1+ 不允许整体改写 $GLOBALS,故不再处理)
    $_GET     = stripslashes_array($_GET);
    $_POST    = stripslashes_array($_POST);
    $_COOKIE  = stripslashes_array($_COOKIE);
    $_REQUEST = stripslashes_array($_REQUEST);
}
