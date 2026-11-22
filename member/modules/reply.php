<?php
/**
 * 用户中心 - 回复我的
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

//回复评论
if ($member_action == 'savereply') {
    if ($cfg_gbookstart == '0') {
        exit;
    }
    $msg = (string)($_POST['msg'] ?? '');
    if ($msg == '') {
        ShowMsg('请输入回复内容！', 'index.php?action=Rc', 0, 3000);
        exit();
    }
    $ischeck = $cfg_gbcheck == 1 ? 0 : 1;
    if ($cfg_feedback_ck == '1') {
        $validate = strtolower(trim((string)($_POST['validate'] ?? '')));
        $svali = strtolower(trim(GetCkVdValue()));
        if ($validate != $svali || $svali == '') {
            ResetVdValue();
            ShowMsg('验证码错误！', 'index.php?action=Rc', 0, 3000);
            exit();
        }
    }
    $ip = GetIP();
    $dtime = time();
    if (!empty($cfg_comment_times)) {
        $last = $dsql->GetOne("SELECT dtime FROM `sea_comment` WHERE `ip` = '$ip' ORDER BY `id` desc ");
        $last = is_array($last) ? $last : array();
        if ($dtime - intval($last['dtime'] ?? 0) < $cfg_comment_times) {
            ShowMsg('评论太快，请休息一下再来评论！', 'index.php?action=Rc', 0, 3000);
            exit();
        }
    }
    $msg = cn_substrR(TrimMsg(unescape($msg)), 1000);
    if (!empty($cfg_banwords)) {
        $myarr = explode('|', $cfg_banwords);
        foreach ($myarr as $word) {
            if ($word !== '' && strpos($msg, $word) !== false) {
                ShowMsg('您发表的评论中有禁用词语！', 'index.php?action=Rc', 0, 3000);
                exit();
            }
        }
    }
    $tmpname = addslashes(cn_substr(RemoveXSS(stripslashes($_SESSION['sea_user_name'])), 20));
    $itype = intval($rmtype ?? 0);
    $rid = intval($rid ?? 0);
    $rvid = intval($rvid ?? 0);
    if ($msg != '') {
        $msg = _Replace_Badword($msg);
        $msgDb = addslashes($msg);
        $inquery = "INSERT INTO `sea_comment`(`v_id`,`uid`,`username`,`ip`,`ischeck`,`reply`,`agree`,`anti`,`dtime`,`msg`,`m_type`) VALUES ('$rvid','$uid','$tmpname','$ip',$ischeck,$rid,0,0,'$dtime','$msgDb','$itype'); ";
        if (!$dsql->ExecuteNoneQuery($inquery)) {
            echo $dsql->GetError();
            exit();
        }
    }
    delfile(sea_ROOT . "/data/cache/review/$itype/$rvid.js");
    ShowMsg('恭喜，成功回复一条评论！', 'index.php?action=Rc', 0, 3000);
    exit();
}

$uname = $_SESSION['sea_user_name'];
$pcount = 20;

$ids = array();
$dsql->SetQuery("SELECT id from sea_comment WHERE username='$uname'");
$dsql->Execute('member_reply_ids');
while ($item = $dsql->GetArray('member_reply_ids')) {
    $ids[] = intval($item['id']);
}
$idStr = empty($ids) ? '0' : implode(',', $ids);

$total = 0;
$pageInfo = member_pagination(0, $pcount);
if ($idStr !== '0') {
    $row = $dsql->GetOne("select count(id) as dd from sea_comment where reply in($idStr)");
    $total = is_array($row) ? intval($row['dd']) : 0;
    $pageInfo = member_pagination($total, $pcount);
}
$offset = $pageInfo['offset'];

$rows = array();
$dsql->SetQuery("select * from sea_comment where reply in($idStr) ORDER BY dtime DESC limit $offset,$pcount");
$dsql->Execute('member_reply');
while ($item = $dsql->GetArray('member_reply')) {
    $content = member_content_link($item['v_id'], $item['m_type']);
    $origin = $dsql->GetOne("select id,msg from sea_comment where id=" . intval($item['reply']));
    $originMsg = is_array($origin) ? $origin['msg'] : '';
    $username = $item['username'] == "" ? '匿名用户' : $item['username'];
    $rows[] = array(
        'id' => intval($item['id']),
        'vid' => intval($item['v_id']),
        'mtype' => intval($item['m_type']),
        'title' => member_e($content['type'] . cn_substrR($content['name'], 30)),
        'link' => member_e($content['link']),
        'time' => MyDate('', $item['dtime']),
        'origin' => $originMsg,
        'msg' => $item['msg'],
        'username' => member_e($username),
        'replyurl' => "javascript:showR(" . intval($item['id']) . "," . intval($item['v_id']) . "," . intval($item['m_type']) . ")"
    );
}
$memberTpl->setLoop('reply', $rows);
member_set_page($pageInfo, 'index.php?action=Rc');
member_set('totalcount', $total);

//验证码
if ($cfg_feedback_ck == '1') {
    $vimgHtml = '<input type="text" name="validate" id="vdcode" placeholder="验证码" class="form-control member-vcode" autocomplete="off"> <img id="vdimgck" src="../include/vdimgck.php" class="member-vimg" alt="验证码" onclick="this.src=\'../include/vdimgck.php?\'+(new Date().getTime());">';
} else {
    $vimgHtml = '';
}
$memberTpl->setVar('vimg', $vimgHtml);
$memberTpl->setVar('vimg_js', json_encode($vimgHtml));
$memberTpl->setVar('gbookstart', $cfg_gbookstart);

member_render('reply.html');
