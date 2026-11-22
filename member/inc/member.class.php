<?php
/**
 * 用户中心模板解析类
 * 仅作用于 /member 模块，继承系统模板类以复用全局标签能力
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}
require_once(sea_INC . '/main.class.php');

class MemberClass_Template extends MainClass_Template
{
    //当前用户中心模板主题
    public $memberStyle = 'default';
    //当前会员数据
    public $memberData = array();
    //普通模板变量
    public $tplVars = array();
    //循环数据
    public $loopData = array();
    //分页信息
    public $pageInfo = array('page' => 1, 'pagecount' => 1, 'total' => 0, 'pagesize' => 20, 'pager' => '');

    function __construct($memberStyle = 'default')
    {
        parent::__construct();
        $style = preg_replace('/[^0-9a-zA-Z_\-]/', '', (string)$memberStyle);
        $base = sea_ROOT . '/member/templets/';
        if ($style === '' || !is_dir($base . $style)) {
            $style = 'default';
        }
        $this->memberStyle = $style;
        //覆盖模板目录为用户中心模板目录
        $this->templateDir = $base . $style . '/';
        $cmspath = isset($GLOBALS['cfg_cmspath']) ? '/' . trim($GLOBALS['cfg_cmspath'], '/') : '';
        if ($cmspath === '/') {
            $cmspath = '';
        }
        $this->setVar('tplurl', $cmspath . '/member/templets/' . $style . '/');
    }

    /**
     * 设置单个模板变量
     */
    function setVar($name, $value)
    {
        $this->tplVars[$name] = $value;
    }

    /**
     * 批量设置模板变量
     */
    function setVars($vars)
    {
        if (is_array($vars)) {
            foreach ($vars as $k => $v) {
                $this->tplVars[$k] = $v;
            }
        }
    }

    /**
     * 设置循环数据
     */
    function setLoop($name, $rows)
    {
        $this->loopData[$name] = is_array($rows) ? $rows : array();
    }

    /**
     * 设置分页信息
     */
    function setPage($page, $pageCount, $total, $pageSize, $pager)
    {
        $this->pageInfo = array(
            'page' => intval($page),
            'pagecount' => max(1, intval($pageCount)),
            'total' => intval($total),
            'pagesize' => intval($pageSize),
            'pager' => $pager
        );
    }

    /**
     * 解析用户中心标签
     */
    function parseMemberTags($content)
    {
        //先解析循环块，避免循环标签被当作普通标签清理
        foreach ($this->loopData as $name => $rows) {
            $content = $this->parseMemberLoop($content, $name, $rows);
        }
        //分页信息
        foreach ($this->pageInfo as $k => $v) {
            $content = str_replace('{member:' . $k . '}', $v, $content);
        }
        //普通变量
        foreach ($this->tplVars as $k => $v) {
            $content = str_replace('{member:' . $k . '}', $v, $content);
        }
        //清理未解析的用户中心标签（如空值）
        $content = preg_replace('/\{member:[a-zA-Z0-9_\-]+\}/is', '', $content);
        return $content;
    }

    /**
     * 解析循环块
     * 模板格式：{member:favorite}...{/member:favorite}，字段使用 [favorite:name]
     */
    function parseMemberLoop($content, $name, $rows)
    {
        $open = '{member:' . $name . '}';
        if (strpos($content, $open) === false) {
            return $content;
        }
        //空数据块 {member:name-empty}...{/member:name-empty}
        $emptyOpen = '{member:' . $name . '-empty}';
        $emptyClose = '{/member:' . $name . '-empty}';
        if (strpos($content, $emptyOpen) !== false) {
            $emptyBlock = '';
            if (preg_match('/' . preg_quote($emptyOpen, '/') . '(.*?)' . preg_quote($emptyClose, '/') . '/is', $content, $em)) {
                $emptyBlock = $em[1];
            }
            if (empty($rows)) {
                $content = str_replace($emptyOpen . $emptyBlock . $emptyClose, $emptyBlock, $content);
            } else {
                $content = str_replace($emptyOpen . $emptyBlock . $emptyClose, '', $content);
            }
        }
        $close = '{/member:' . $name . '}';
        $rule = '/' . preg_quote($open, '/') . '(.*?)' . preg_quote($close, '/') . '/is';
        if (!preg_match_all($rule, $content, $matches)) {
            return $content;
        }
        foreach ($matches[0] as $idx => $whole) {
            $tpl = $matches[1][$idx];
            $out = '';
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $item = $tpl;
                    if (is_array($row)) {
                        foreach ($row as $fk => $fv) {
                            $item = str_replace('[' . $name . ':' . $fk . ']', $fv, $item);
                        }
                    }
                    //清理未提供的字段
                    $item = preg_replace('/\[' . preg_quote($name, '/') . ':[a-zA-Z0-9_\-]+\]/is', '', $item);
                    $out .= $item;
                }
            }
            $content = str_replace($whole, $out, $content);
        }
        return $content;
    }

    /**
     * 完整解析用户中心页面
     */
    function parseMemberPage($content)
    {
        //页面结构、公共标签
        $content = $this->parseTopAndFoot($content);
        $content = str_replace('{seacms:currenttypeid}', '-444', $content);
        $content = str_replace('{seacms:member}', front_member(), $content);
        //用户中心标签
        $content = $this->parseMemberTags($content);
        //全局标签
        $content = $this->parseSelf($content);
        $content = $this->parseHistory($content);
        $content = $this->parseGlobal($content);
        $content = $this->parseAreaList($content);
        $content = $this->parseNewsAreaList($content);
        $content = $this->parseMenuList($content, '', -444);
        $content = $this->parseVideoList($content, -444, '', '');
        $content = $this->parseNewsList($content, -444, '');
        $content = $this->parseTopicList($content);
        $content = $this->parseLinkList($content);
        $content = $this->parseIf($content);
        $content = str_replace('{seacms:runinfo}', '', $content);
        return $content;
    }
}
