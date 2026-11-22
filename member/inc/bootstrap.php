<?php
/**
 * 用户中心引导文件：配置、登录校验、模板初始化与路由
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

$member_action = isset($action) ? trim((string)$action) : 'cc';
$member_mod = isset($mod) ? trim((string)$mod) : '';
if ($member_action === '') {
    $member_action = 'cc';
}
$uid = intval($_SESSION['sea_user_id'] ?? 0);
$year = date('Y');

if ($cfg_user == 0) {
    ShowMsg('系统已关闭会员功能!', '../index.php');
    exit();
}

//会员组折扣默认值
if ($cfg_vipoff_1 == 0 OR $cfg_vipoff_1 == "" OR empty($cfg_vipoff_1)) {
    $cfg_vipoff_1 = 1;
}
if ($cfg_vipoff_3 == 0 OR $cfg_vipoff_3 == "" OR empty($cfg_vipoff_3)) {
    $cfg_vipoff_3 = 1;
}
if ($cfg_vipoff_6 == 0 OR $cfg_vipoff_6 == "" OR empty($cfg_vipoff_6)) {
    $cfg_vipoff_6 = 1;
}
if ($cfg_vipoff_12 == 0 OR $cfg_vipoff_12 == "" OR empty($cfg_vipoff_12)) {
    $cfg_vipoff_12 = 1;
}

//初始化用户中心模板
$memberStyle = isset($GLOBALS['cfg_member_style']) ? $GLOBALS['cfg_member_style'] : 'default';
$memberTpl = new MemberClass_Template($memberStyle);

//基础模板变量
$memberBaseVars = array(
    'username' => member_e($_SESSION['sea_user_name'] ?? ''),
    'userid' => $uid,
    'group' => '',
    'points' => 0,
    'pointsname' => member_e($cfg_pointsname ?? ''),
    'email' => '',
    'nickname' => '',
    'logincount' => 0,
    'vipendtime' => '',
    'pic' => member_e('/uploads/user/a.png'),
    'signhtml' => '',
    'promolink' => member_e($_SERVER['HTTP_HOST'] ?? '') . '/member/i.php?uid=' . $uid,
    'kamiurl' => member_e($cfg_kami ?? ''),
    'kamistyle' => empty($cfg_kami) ? 'display:none;' : '',
    'exiturl' => 'exit.php',
    'homeurl' => '../index.php',
    'curr' => $member_action,
    'loggedin' => '0',
    'year' => $year,
    'sitename' => member_e($cfg_webname ?? ''),
    'notice1' => '',
    'notice1style' => 'display:none;',
    'notice2' => '',
    'notice2style' => 'display:none;',
    'notice3' => '',
    'notice3style' => 'display:none;',
    'membermsg' => '',
    'membermsgstyle' => 'display:none;',
    'csrftoken' => member_csrf_token(),
    'upgradelist' => ''
);
$memberTpl->setVars($memberBaseVars);

//未登录时仅允许游客流程（激活 / 找回密码）
if ($member_mod === 'activate' || $member_mod === 'repsw' || $member_mod === 'repsw2' || $member_mod === 'repsw3' || $member_mod === 'repsw4') {
    require_once(dirname(__FILE__) . '/../modules/password.php');
    exit();
}

//登录校验
$front = 'front';
$hashstr = md5($cfg_dbpwd . $cfg_dbname . $cfg_dbuser . $front);
if (empty($uid) OR ($_SESSION['hashstr'] ?? '') !== $hashstr) {
    showMsg("请先登录", "login.php");
    exit();
}

//加载当前会员资料
$memberRow = member_get_row($uid);
if (empty($memberRow)) {
    showMsg("会员信息不存在，请重新登录", "login.php");
    exit();
}
$memberTpl->memberData = $memberRow;

//刷新会员组（会籍到期自动降级）
$memberGid = intval($memberRow['gid'] ?? 2);
if (intval($memberRow['vipendtime'] ?? 0) < time() && $memberGid != 2) {
    $memberGid = 2;
    $dsql->ExecuteNoneQuery("update `sea_member` set gid=2 where id=$uid");
    $_SESSION['sea_user_group'] = 2;
} else {
    $_SESSION['sea_user_group'] = $memberGid;
}
$memberGroupName = member_group_name($memberGid);
$memberPicture = trim((string)($memberRow['pic'] ?? ''));
if ($memberPicture === '') {
    $memberPicture = 'uploads/user/a.png';
}
if (!preg_match('#^(https?:)?//#', $memberPicture)) {
    $memberPicture = '/' . ltrim($memberPicture, '/');
}

$memberTpl->setVars(array(
    'username' => member_e($_SESSION['sea_user_name'] ?? ''),
    'userid' => $uid,
    'group' => member_e($memberGroupName),
    'groupid' => $memberGid,
    'points' => intval($memberRow['points'] ?? 0),
    'email' => member_e($memberRow['email'] ?? ''),
    'nickname' => member_e($memberRow['nickname'] ?? ''),
    'logincount' => intval($memberRow['logincount'] ?? 0),
    'vipendtime' => $memberGid == 2 ? '无限期' : date('Y-m-d H:i:s', intval($memberRow['vipendtime'] ?? 0)),
    'pic' => member_e($memberPicture),
    'loggedin' => '1',
    'signhtml' => member_sign_html()
));

//站内通知
require_once(dirname(__DIR__, 2) . '/data/admin/notify.php');
if (!empty($notify1)) {
    $memberTpl->setVars(array('notice1' => $notify1, 'notice1style' => ''));
}
if (!empty($notify2)) {
    $memberTpl->setVars(array('notice2' => $notify2, 'notice2style' => ''));
}
if (!empty($notify3)) {
    $memberTpl->setVars(array('notice3' => $notify3, 'notice3style' => ''));
}
$msgbody = trim((string)($memberRow['msgbody'] ?? ''));
if ($msgbody !== '' && ($memberRow['msgstate'] ?? '') != 'y') {
    $memberTpl->setVars(array('membermsg' => nl2br(member_e($msgbody)), 'membermsgstyle' => ''));
}

//升级会员组可选项
$groupRows = array();
$groupUpgrade = 0;
$groupRow = $dsql->GetOne("select * from sea_member_group where gid=$memberGid");
if (is_array($groupRow)) {
    $groupUpgrade = intval($groupRow['g_upgrade'] ?? 0);
}
$dsql->SetQuery("select * from sea_member_group where g_upgrade >= $groupUpgrade and gid>2 order by g_upgrade asc");
$dsql->Execute('member_group_list');
while ($groupItem = $dsql->GetObject('member_group_list')) {
    $groupRows[] = array(
        'gid' => intval($groupItem->gid),
        'gname' => member_e($groupItem->gname),
        'upgrade' => intval($groupItem->g_upgrade),
        'label' => ($memberGid == $groupItem->gid) ? '续费 ' : ''
    );
}
$memberTpl->setLoop('grouplist', $groupRows);

//按功能模块路由
$memberRoutes = array(
    'cc' => 'profile.php',
    'settings' => 'settings.php',
    'chgpwdsubmit' => 'settings.php',
    'delmsg' => 'profile.php',
    'favorite' => 'favorite.php',
    'cancelfav' => 'favorite.php',
    'cancelfavs' => 'favorite.php',
    'buy' => 'buy.php',
    'buy2' => 'membership.php',
    'hyz' => 'membership.php',
    'buy3' => 'recharge.php',
    'cz' => 'recharge.php',
    'netpay' => 'netpay.php',
    'gb' => 'guestbook.php',
    'del_gb' => 'guestbook.php',
    'VNc' => 'comment.php',
    'del_pl' => 'comment.php',
    'Rc' => 'reply.php',
    'savereply' => 'reply.php',
    'err' => 'err.php',
    'del_err' => 'err.php'
);

$memberModule = isset($memberRoutes[$member_action]) ? $memberRoutes[$member_action] : 'profile.php';
require_once(dirname(__FILE__) . '/../modules/' . $memberModule);
