<?php
/**
 * MySQL 兼容层(mysqli 实现 mysql_* 语义)
 *
 * PHP 8 已彻底移除 mysql_* 系列函数。本文件用 mysqli 重新实现
 * 帝国备份王(ebak)依赖的 13 个 mysql_* 函数,并保留旧版
 * "默认连接"语义:调用时不显式传 link 的函数会自动使用
 * 最近一次 mysql_connect() 建立的连接。
 *
 * @package SeaCMS
 */

if (!defined('EBAK_MYSQL_COMPAT')) {
    define('EBAK_MYSQL_COMPAT', true);

    /**
     * 最近一次 mysql_connect() 建立的连接(默认连接)
     *
     * @var mysqli|null
     */
    $GLOBALS['_ebak_default_mysqli'] = null;

    function _ebak_link(&$link = null)
    {
        global $_ebak_default_mysqli;

        if ($link instanceof mysqli) {
            return $link;
        }
        if ($_ebak_default_mysqli instanceof mysqli) {
            return $_ebak_default_mysqli;
        }
        // 兼容传入"连接资源句柄"的历史用法:尝试从全局注册表还原
        if (is_string($link) && isset($GLOBALS['_ebak_links'][$link])) {
            return $GLOBALS['_ebak_links'][$link];
        }
        trigger_error('No MySQL connection available', E_USER_WARNING);

        return null;
    }

    /**
     * 打开一个 MySQL 连接
     */
    function mysql_connect($host = '', $user = '', $pwd = '', $new = false, $flags = 0)
    {
        global $_ebak_default_mysqli;

        $port = 3306;
        // 兼容旧版 "host:port" 写法
        if (preg_match('/^(.*):(\d+)$/', $host, $m)) {
            $host = $m[1];
            $port = (int) $m[2];
        }
        $conn = @mysqli_connect($host, $user, $pwd, '', $port);
        if ($conn) {
            $_ebak_default_mysqli = $conn;
            // 分配一个稳定的句柄 ID,兼容按句柄引用连接的历史代码
            static $handleSeq = 0;
            $handleSeq++;
            $handle = 'res_' . $handleSeq;
            $GLOBALS['_ebak_links'][$handle] = $conn;

            return $handle;
        }

        return false;
    }

    function mysql_select_db($dbname, $link = null)
    {
        return mysqli_select_db(_ebak_link($link), $dbname);
    }

    function mysql_query($sql, $link = null)
    {
        return mysqli_query(_ebak_link($link), $sql);
    }

    function mysql_real_escape_string($str, $link = null)
    {
        return mysqli_real_escape_string(_ebak_link($link), $str);
    }

    function mysql_set_charset($charset, $link = null)
    {
        return mysqli_set_charset(_ebak_link($link), $charset);
    }

    function mysql_error($link = null)
    {
        return mysqli_error(_ebak_link($link));
    }

    function mysql_errno($link = null)
    {
        return mysqli_errno(_ebak_link($link));
    }

    function mysql_close($link = null)
    {
        return mysqli_close(_ebak_link($link));
    }

    function mysql_fetch_array($result, $type = MYSQLI_BOTH)
    {
        return $result ? mysqli_fetch_array($result, $type) : false;
    }

    function mysql_num_rows($result)
    {
        return $result ? mysqli_num_rows($result) : 0;
    }

    function mysql_data_seek($result, $offset)
    {
        return mysqli_data_seek($result, $offset);
    }

    function mysql_free_result($result)
    {
        return $result ? mysqli_free_result($result) : true;
    }

    function mysql_insert_id($link = null)
    {
        return mysqli_insert_id(_ebak_link($link));
    }

    function mysql_get_server_info($link = null)
    {
        return mysqli_get_server_info(_ebak_link($link));
    }

    /**
     * mysql_result 的 mysqli 实现
     */
    function mysql_result($result, $row, $field = 0)
    {
        mysqli_data_seek($result, $row);
        $rowArr = mysqli_fetch_array($result);
        if ($rowArr === false) {
            return false;
        }
        if (is_int($field)) {
            $keys = array_keys($rowArr);
            $values = array_values($rowArr);

            return $values[$field * 2] ?? false; // MYSQLI_BOTH 下偶数位为数字索引
        }

        return $rowArr[$field] ?? false;
    }
}
