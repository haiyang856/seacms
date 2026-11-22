<?php
/**
 * 用户中心 - 充值记录 / 充值卡充值
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

//充值卡充值
if ($member_action == 'cz') {
    $key = (string)($_POST['cckkey'] ?? '');
    $key = RemoveXSS(stripslashes($key));
    $key = addslashes(cn_substr($key, 200));
    if ($key == "") {
        ShowMsg("没有输入充值卡号", "-1");
        exit;
    }
    $card = $dsql->GetOne("SELECT * FROM sea_cck where ckey='$key'");
    if (!is_array($card) OR $card['status'] != 0) {
        ShowMsg("充值卡不正确或已被使用", "-1");
        exit;
    }
    $uname = $_SESSION['sea_user_name'];
    $points = intval($card['climit']);
    $unameDb = addslashes($uname);
    $dsql->ExecuteNoneQuery("UPDATE sea_cck SET usetime=NOW(),uname='$unameDb',status='1' WHERE ckey='$key'");
    $dsql->ExecuteNoneQuery("UPDATE sea_member SET points=points+$points WHERE username='$unameDb'");
    ShowMsg("恭喜！充值成功！", "index.php?action=cc");
    exit;
}

$uname = $_SESSION['sea_user_name'];
$row = $dsql->GetOne("select count(id) as dd from sea_cck where status=1 and uname='$uname'");
$total = is_array($row) ? intval($row['dd']) : 0;
$pageInfo = member_pagination($total, 20);
$offset = $pageInfo['offset'];
$pageSize = $pageInfo['pagesize'];

$rows = array();
$dsql->SetQuery("select * from sea_cck where status=1 and uname='$uname' ORDER BY usetime DESC limit $offset,$pageSize");
$dsql->Execute('member_recharge');
while ($item = $dsql->GetArray('member_recharge')) {
    $rows[] = array(
        'time' => MyDate('', strtotime($item['usetime'])),
        'points' => intval($item['climit']),
        'ckey' => member_e($item['ckey'])
    );
}
$memberTpl->setLoop('recharge', $rows);
member_set_page($pageInfo, 'index.php?action=buy3');
member_set('totalcount', $total);

member_render('recharge.html');
