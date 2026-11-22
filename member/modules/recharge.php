<?php
/**
 * 用户中心 - 充值记录（充值卡 / 在线充值 / 分销提成）与充值卡充值
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}
require_once(sea_INC . '/netpay/order.php');

//充值卡充值
if ($member_action == 'cz') {
    member_csrf_check();
    $key = isset($_POST['cckkey']) ? trim((string)$_POST['cckkey']) : '';
    $key = RemoveXSS(stripslashes($key));
    if ($key === '' || !preg_match('/^[0-9A-Za-z_\-]{6,80}$/', $key)) {
        ShowMsg('充值卡号格式不正确', 'index.php?action=buy3');
        exit;
    }
    $keyEsc = addslashes($key);
    $card = $dsql->GetOne("SELECT * FROM sea_cck WHERE ckey='{$keyEsc}' limit 1");
    if (!is_array($card)) {
        ShowMsg('充值卡不存在，请核对后重试', 'index.php?action=buy3');
        exit;
    }
    if (intval($card['status']) !== 0) {
        ShowMsg('该充值卡已被使用', 'index.php?action=buy3');
        exit;
    }
    $points = intval($card['climit']);
    if ($points <= 0) {
        ShowMsg('充值卡面值异常，请联系管理员', 'index.php?action=buy3');
        exit;
    }
    $cardId = intval($card['id']);
    $unameEsc = addslashes((string)$_SESSION['sea_user_name']);
    $ip = netpay_client_ip();
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? addslashes(substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255)) : '';
    $now = time();
    $orderno = netpay_new_order_no();

    $inTransaction = false;
    if (function_exists('mysqli_begin_transaction') && is_object($dsql) && !empty($dsql->linkID)) {
        @mysqli_begin_transaction($dsql->linkID);
        $inTransaction = true;
    }
    $affected = $dsql->ExecuteNoneQuery2("UPDATE sea_cck SET status='1',usetime=NOW(),uname='{$unameEsc}',uid={$uid},rtype=1 WHERE id={$cardId} AND status='0'");
    if (intval($affected) !== 1) {
        if ($inTransaction) {
            @mysqli_rollback($dsql->linkID);
        }
        ShowMsg('该充值卡已被使用', 'index.php?action=buy3');
        exit;
    }
    $ok1 = $dsql->ExecuteNoneQuery("UPDATE sea_member SET points=points+{$points} WHERE id={$uid}");
    $insertSql = "INSERT INTO sea_paylog
        (orderno,uid,uname,rtype,channel,amount,currency,points,ratio,status,trade_no,card_id,card_key,parent_uid,dist_level,dist_rate,remark,ip,user_agent,ext,addtime,paytime)
        VALUES
        ('" . addslashes($orderno) . "',{$uid},'{$unameEsc}',1,'card',0,'POINTS',{$points},0,1,'',{$cardId},'" . $keyEsc . "',0,0,0,'充值卡充值','{$ip}','{$ua}','',{$now},{$now})";
    $ok2 = $dsql->ExecuteNoneQuery($insertSql);
    if (!$ok1 || !$ok2) {
        if ($inTransaction) {
            @mysqli_rollback($dsql->linkID);
        }
        netpay_log('充值卡入账失败', array('uid' => $uid, 'card_id' => $cardId, 'error' => $dsql->GetError()));
        ShowMsg('充值失败，请稍后再试', 'index.php?action=buy3');
        exit;
    }
    if (!empty($dsql->linkID)) {
        $paylogId = intval(mysqli_insert_id($dsql->linkID));
        if ($paylogId > 0) {
            $dsql->ExecuteNoneQuery("UPDATE sea_cck SET paylog_id={$paylogId} WHERE id={$cardId}");
        }
    }
    if ($inTransaction) {
        @mysqli_commit($dsql->linkID);
    }
    ShowMsg('恭喜！充值成功，' . $points . ' 积分已到账！', 'index.php?action=buy3');
    exit;
}

//记录类型筛选：0 全部 1 充值卡 2 在线支付 3 分销 7 其它（签到/推广链接/注册赠送）
$currt = isset($_GET['t']) ? intval($_GET['t']) : 0;
if (!in_array($currt, array(0, 1, 2, 3, 7), true)) {
    $currt = 0;
}
$where = "uid={$uid}";
if ($currt === 7) {
    $where .= " AND rtype IN (4,5,6)";
} elseif ($currt > 0) {
    $where .= " AND rtype={$currt}";
}
$row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_paylog WHERE {$where}");
$total = is_array($row) ? intval($row['dd']) : 0;
$pageInfo = member_pagination($total, 20);
$offset = $pageInfo['offset'];
$pageSize = $pageInfo['pagesize'];

$rows = array();
$dsql->SetQuery("SELECT * FROM sea_paylog WHERE {$where} ORDER BY id DESC limit {$offset},{$pageSize}");
$dsql->Execute('member_recharge');
while ($item = $dsql->GetArray('member_recharge')) {
    $rtype = intval($item['rtype']);
    $status = intval($item['status']);
    $currency = strtoupper($item['currency']);
    $amount = number_format(floatval($item['amount']), 2, '.', '');
    if ($currency === 'USDT') {
        $amountText = rtrim(rtrim($amount, '0'), '.') . ' USDT';
    } elseif (floatval($amount) > 0 && $currency === 'CNY') {
        $amountText = '￥' . $amount;
    } else {
        $amountText = '-';
    }
    $rows[] = array(
        'time'        => date('Y-m-d H:i:s', intval($item['addtime'])),
        'type'        => member_e(netpay_rtype_name($rtype)),
        'typeclass'   => 'rtype-' . $rtype,
        'channel'     => member_e(netpay_channel_name($item['channel'])),
        'amount'      => member_e($amountText),
        'points'      => intval($item['points']),
        'status'      => member_e(netpay_status_name($status)),
        'statusclass' => in_array($status, array(0, 1, 4), true) ? 'pstatus-' . $status : 'pstatus-unknown',
        'orderno'     => member_e($item['orderno']),
        'remark'      => member_e($item['remark']),
    );
}
$memberTpl->setLoop('recharge', $rows);
member_set_page($pageInfo, 'index.php?action=buy3' . ($currt > 0 ? '&t=' . $currt : ''));

$summary = netpay_summary($uid);
member_sets(array(
    'total'           => $total,
    'currt'           => $currt,
    'sum_card_points' => number_format($summary['card_points']),
    'sum_online_cny'  => number_format($summary['online_cny'], 2, '.', ''),
    'sum_online_usdt' => rtrim(rtrim(number_format($summary['online_usdt'], 2, '.', ''), '0'), '.'),
    'sum_dist_points' => number_format($summary['dist_points']),
    'sum_sign_points' => number_format($summary['sign_points']),
    'sum_promo_points' => number_format($summary['promo_points']),
    'sum_reg_points' => number_format($summary['reg_points']),
));

member_render('recharge.html');
