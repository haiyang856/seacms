<?php
/**
 * 用户中心公共函数
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

/**
 * HTML 转义
 */
function member_e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * 生成用户中心操作链接
 */
function member_url($action = '', $extra = '')
{
    $url = 'index.php';
    $query = '';
    if ($action !== '') {
        $query = 'action=' . urlencode($action);
    }
    if ($extra !== '') {
        $query .= ($query === '' ? '' : '&') . $extra;
    }
    if ($query !== '') {
        $url .= '?' . $query;
    }
    return $url;
}

/**
 * 分页条（保持原有样式类名，便于主题覆盖）
 */
function member_pager_html($baseUrl, $page, $pageCount, $label = '')
{
    $page = max(1, intval($page));
    $pageCount = max(1, intval($pageCount));
    $pre = $page <= 1 ? 1 : $page - 1;
    $next = $page >= $pageCount ? $pageCount : $page + 1;
    $sep = strpos($baseUrl, '?') === false ? '?' : '&';
    $html = '<div class="sea_page"><div class="sea_page_box">';
    $html .= '<a href="' . $baseUrl . $sep . 'page=1">‹‹</a>';
    $html .= '<a href="' . $baseUrl . $sep . 'page=' . $pre . '">‹</a>';
    $html .= '<span class="sea_num">' . $page . '/' . $pageCount . '</span>';
    $html .= '<a href="' . $baseUrl . $sep . 'page=' . $next . '">›</a>';
    $html .= '<a href="' . $baseUrl . $sep . 'page=' . $pageCount . '">››</a>';
    $html .= '</div>';
    if ($label !== '') {
        $html .= '<div class="sea_page_info">' . member_e($label) . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * 计算分页参数
 */
function member_pagination($total, $pageSize = 20)
{
    $pageSize = max(1, intval($pageSize));
    $total = max(0, intval($total));
    $pageCount = max(1, (int)ceil($total / $pageSize));
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    if ($page < 1) {
        $page = 1;
    }
    if ($page > $pageCount) {
        $page = $pageCount;
    }
    return array(
        'page' => $page,
        'pagecount' => $pageCount,
        'total' => $total,
        'pagesize' => $pageSize,
        'offset' => ($page - 1) * $pageSize
    );
}

/**
 * 设置模板变量
 */
function member_set($name, $value)
{
    global $memberTpl;
    if (is_object($memberTpl)) {
        $memberTpl->setVar($name, $value);
    }
}

/**
 * 批量设置模板变量
 */
function member_sets($vars)
{
    global $memberTpl;
    if (is_object($memberTpl)) {
        $memberTpl->setVars($vars);
    }
}

/**
 * 设置循环数据
 */
function member_loop($name, $rows)
{
    global $memberTpl;
    if (is_object($memberTpl)) {
        $memberTpl->setLoop($name, $rows);
    }
}

/**
 * 设置分页数据
 */
function member_set_page($pageInfo, $baseUrl)
{
    global $memberTpl;
    if (is_object($memberTpl)) {
        $pager = member_pager_html($baseUrl, $pageInfo['page'], $pageInfo['pagecount'], '共 ' . $pageInfo['total'] . ' 条');
        $memberTpl->setPage($pageInfo['page'], $pageInfo['pagecount'], $pageInfo['total'], $pageInfo['pagesize'], $pager);
    }
}

/**
 * 输出用户中心模板
 */
function member_render($template)
{
    global $memberTpl;
    if (!is_object($memberTpl)) {
        exit('模板引擎未初始化');
    }
    $file = $memberTpl->templateDir . $template;
    if (!is_file($file)) {
        //模板缺失时回退到默认主题
        $fallback = sea_ROOT . '/member/templets/default/' . $template;
        if ($memberTpl->memberStyle !== 'default' && is_file($fallback)) {
            $memberTpl->templateDir = sea_ROOT . '/member/templets/default/';
            $memberTpl->memberStyle = 'default';
            $file = $fallback;
        } else {
            ShowMsg('用户中心模板文件不存在：' . member_e($template), 'index.php');
            exit();
        }
    }
    $content = loadFile($file);
    echo $memberTpl->parseMemberPage($content);
}

/**
 * 获取会员组名称
 */
function member_group_name($gid)
{
    global $dsql;
    $gid = intval($gid);
    $row = $dsql->GetOne("select gname from sea_member_group where gid=$gid");
    return is_array($row) && isset($row['gname']) ? $row['gname'] : '';
}

/**
 * 获取会员资料
 */
function member_get_row($uid)
{
    global $dsql;
    $uid = intval($uid);
    if ($uid <= 0) {
        return array();
    }
    $row = $dsql->GetOne("select * from sea_member where id=$uid");
    return is_array($row) ? $row : array();
}

/**
 * 根据评论/报错关联类型获取内容标题与链接
 * @return array [link,name,type]
 */
function member_content_link($vid, $mtype = 0)
{
    global $dsql;
    $vid = intval($vid);
    $link = '';
    $name = '';
    $type = '';
    if (intval($mtype) === 0) {
        $rs = $dsql->GetOne("select tid,v_id,v_addtime,v_enname,v_name from sea_data where v_id=$vid");
        if (is_array($rs)) {
            $link = getContentLink($rs['tid'], $rs['v_id'], "", date('Y-n', $rs['v_addtime']), $rs['v_enname']);
            $name = $rs['v_name'];
        }
        $type = '[视频]';
    } elseif (intval($mtype) === 1) {
        $rs = $dsql->GetOne("select tid,n_id,n_title from sea_news where n_id=$vid");
        if (is_array($rs)) {
            $link = getArticleLink($rs['tid'], $rs['n_id'], '');
            $name = $rs['n_title'];
        }
        $type = '[新闻]';
    }
    return array('link' => $link, 'name' => $name, 'type' => $type);
}

/**
 * 签到按钮
 */
function member_sign_html()
{
    global $dsql, $cfg_spoints, $cfg_pointsname;
    if (empty($cfg_spoints)) {
        return '';
    }
    $uid = intval($_SESSION['sea_user_id'] ?? 0);
    if ($uid <= 0) {
        return '';
    }
    $row = $dsql->GetOne("select stime from sea_member where id=$uid");
    $lasttime = is_array($row) ? intval($row['stime']) : 0;
    if (time() - $lasttime > 86400) {
        return '<a href="s.php" class="btn btn-warning btn-sm"><i class="icon iconfont icon-sign"></i> 我要签到 <span class="badge">+' . member_e($cfg_pointsname) . '</span></a>';
    }
    return '<button class="btn btn-default btn-sm" type="button" disabled><i class="icon iconfont icon-sign"></i> 今日已经签到</button>';
}
