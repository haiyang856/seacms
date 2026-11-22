<?php
/**
 * 用户中心 - 会员购买记录 / 购买会员组
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

//购买会员组
if ($member_action == 'hyz') {
    $gidBuy = intval($gid ?? 0);
    if (empty($gidBuy)) {
        ShowMsg("请选择要购买的会员组", "index.php?action=cc");
        exit;
    }
    $mon = intval($mon ?? 0);
    if (empty($mon)) {
        ShowMsg("请选择要购买的时长", "index.php?action=cc");
        exit;
    }
    $groupInfo = $dsql->GetOne("SELECT * FROM sea_member_group where gid='$gidBuy'");
    if (!is_array($groupInfo)) {
        ShowMsg("会员组不存在", "-1");
        exit;
    }
    $hyzjf = intval($groupInfo['g_upgrade']) * $mon;

    $uname = $_SESSION['sea_user_name'];
    $userInfo = $dsql->GetOne("SELECT points,vipendtime FROM sea_member where username='$uname'");
    if (!is_array($userInfo)) {
        ShowMsg("会员信息不存在", "-1");
        exit;
    }
    $userjf = intval($userInfo['points']);

    switch ($mon) {
        case 1:
            $cfg_vipoff = $cfg_vipoff_1;
            break;
        case 3:
            $cfg_vipoff = $cfg_vipoff_3;
            break;
        case 6:
            $cfg_vipoff = $cfg_vipoff_6;
            break;
        case 12:
            $cfg_vipoff = $cfg_vipoff_12;
            break;
        default:
            $cfg_vipoff = $cfg_vipoff_1;
    }
    $hyzjf = $hyzjf * $cfg_vipoff;

    if ($userjf < $hyzjf) {
        ShowMsg("积分不足", "-1");
        exit;
    }
    $ntime = time();
    if (intval($_SESSION['sea_user_group']) == $gidBuy) {
        $ntime = intval($userInfo['vipendtime']);
    }
    $vipendtime = $ntime + 2678400 * $mon;
    $dsql->ExecuteNoneQuery("UPDATE sea_member SET points=points-$hyzjf,gid=$gidBuy,vipendtime=$vipendtime where username='$uname'");
    $dsql->ExecuteNoneQuery("insert into sea_hyzbuy values('','$uname','$gidBuy','$hyzjf','$mon','" . time() . "')");
    // 三级分销：会员开通计佣
    require_once(sea_ROOT . '/include/dist/bootstrap.php');
    dist_commission_on_consume($uid, intval($hyzjf), 2, intval($gidBuy), dist_new_no('H'));
    dist_try_activate($uid);
    ShowMsg("恭喜！购买会员组成功！", "index.php?action=cc");
    exit;
}

$uname = $_SESSION['sea_user_name'];
$row = $dsql->GetOne("select count(id) as dd from sea_hyzbuy where uname='$uname'");
$total = is_array($row) ? intval($row['dd']) : 0;
$pageInfo = member_pagination($total, 20);
$offset = $pageInfo['offset'];
$pageSize = $pageInfo['pagesize'];

$rows = array();
$dsql->SetQuery("select * from sea_hyzbuy where uname='$uname' ORDER BY paytime DESC limit $offset,$pageSize");
$dsql->Execute('member_membership');
while ($item = $dsql->GetArray('member_membership')) {
    $rows[] = array(
        'time' => MyDate('', $item['paytime']),
        'mon' => intval($item['mon']),
        'points' => intval($item['paypoints']),
        'gname' => member_e(member_group_name($item['gid']))
    );
}
$memberTpl->setLoop('membership', $rows);
member_set_page($pageInfo, 'index.php?action=buy2');
member_set('totalcount', $total);

member_render('membership.html');
