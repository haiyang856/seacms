<?php
/**
 * 用户中心 - 基本资料
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

//删除站内消息
if ($member_action == 'delmsg') {
    $dsql->ExecuteNoneQuery("update `sea_member` set msgstate = 'y' where id= '$uid'");
    ShowMsg('站内信息删除成功！', 'index.php?action=cc');
    exit();
}

//刷新会员资料
$memberRow = member_get_row($uid);
$memberTpl->memberData = $memberRow;
$memberGid = intval($memberRow['gid'] ?? 2);
$memberGroupName = member_group_name($memberGid);
$memberPicture = trim((string)($memberRow['pic'] ?? ''));
if ($memberPicture === '') {
    $memberPicture = 'uploads/user/a.png';
}
if (!preg_match('#^(https?:)?//#', $memberPicture)) {
    $memberPicture = '/' . ltrim($memberPicture, '/');
}
$memberTpl->setVars(array(
    'group' => member_e($memberGroupName),
    'groupid' => $memberGid,
    'points' => intval($memberRow['points'] ?? 0),
    'email' => member_e($memberRow['email'] ?? ''),
    'nickname' => member_e($memberRow['nickname'] ?? ''),
    'logincount' => intval($memberRow['logincount'] ?? 0),
    'vipendtime' => $memberGid == 2 ? '无限期' : date('Y-m-d H:i:s', intval($memberRow['vipendtime'] ?? 0)),
    'pic' => member_e($memberPicture),
    'msgbody' => member_e(nl2br((string)($memberRow['msgbody'] ?? ''))),
    'oldpassword' => member_e($memberRow['password'] ?? '')
));
$memberTpl->setVars(array(
    'vipoff1' => $cfg_vipoff_1,
    'vipoff3' => $cfg_vipoff_3,
    'vipoff6' => $cfg_vipoff_6,
    'vipoff12' => $cfg_vipoff_12
));

member_render('index.html');
