<?php
/**
 * 充值订单：创建、支付分发、到账、统计
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}
require_once(dirname(__FILE__) . '/config.php');

/**
 * 防止同一用户高频创建订单
 */
function netpay_rate_limited($uid)
{
    global $dsql;
    $uid = intval($uid);
    if ($uid <= 0) {
        return false;
    }
    $since = time() - 60;
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_paylog WHERE uid={$uid} AND status=0 AND addtime>{$since}");
    return is_array($row) && intval($row['dd']) >= 5;
}

/**
 * 创建待支付订单
 * @return array ok/error/order
 */
function netpay_create_order($uid, $uname, $amount, $currency, $channel, $extra = array())
{
    global $dsql;
    $uid = intval($uid);
    $currency = strtoupper($currency);
    $channel = strtolower($channel);
    if ($uid <= 0) {
        return array('ok' => false, 'error' => '请先登录');
    }
    if (!in_array($currency, array('CNY', 'USDT'), true)) {
        return array('ok' => false, 'error' => '不支持的币种');
    }
    if (!in_array($channel, array('alipay', 'wechat', 'mazfu', 'usdt'), true)) {
        return array('ok' => false, 'error' => '不支持的支付方式');
    }
    $amount = netpay_normalize_amount($amount);
    if ($amount === false) {
        return array('ok' => false, 'error' => '充值金额需在 ' . netpay_cfg('pay_min_money') . ' - ' . netpay_cfg('pay_max_money') . ' 之间');
    }
    // USDT 通道金额单位必须为 USDT，其余为人民币
    if ($channel === 'usdt' && $currency !== 'USDT') {
        return array('ok' => false, 'error' => 'USDT 支付币种不匹配');
    }
    if ($channel !== 'usdt' && $currency !== 'CNY') {
        return array('ok' => false, 'error' => '该支付方式仅支持人民币');
    }
    $points = netpay_points_for($amount, $currency);
    if ($points <= 0) {
        return array('ok' => false, 'error' => '积分兑换比例未配置或配置无效');
    }
    if (netpay_rate_limited($uid)) {
        return array('ok' => false, 'error' => '操作过于频繁，请稍后再试');
    }

    $sub = isset($extra['sub']) ? strtolower((string)$extra['sub']) : '';
    if ($channel === 'mazfu' && !in_array($sub, array('alipay', 'wxpay'), true)) {
        return array('ok' => false, 'error' => '请选择码支付的支付方式');
    }
    $ext = array();
    if ($channel === 'mazfu') {
        $ext['mazfu_type'] = $sub;
    }
    if ($channel === 'usdt') {
        $ext['usdt_trade_type'] = netpay_cfg('usdt_trade_type');
    }
    if (!empty($extra['test'])) {
        $ext['test'] = 1;
    }

    $orderno = netpay_new_order_no();
    $unameEsc = addslashes((string)$uname);
    $channelEsc = addslashes($channel);
    $extJson = !empty($ext) ? json_encode($ext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    $extEsc = addslashes($extJson);
    $ip = netpay_client_ip();
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
    $uaEsc = addslashes($ua);
    $ratio = netpay_ratio_for($currency);
    $now = time();

    $sql = "INSERT INTO sea_paylog
        (orderno,uid,uname,rtype,channel,amount,currency,points,ratio,status,trade_no,card_id,card_key,parent_uid,dist_level,dist_rate,remark,ip,user_agent,ext,addtime,paytime)
        VALUES
        ('" . addslashes($orderno) . "',{$uid},'{$unameEsc}',2,'{$channelEsc}'," . number_format($amount, 2, '.', '') . ",'{$currency}',{$points}," . number_format($ratio, 4, '.', '') . ",0,'',0,'',0,0,0,'','{$ip}','{$uaEsc}','{$extEsc}',{$now},0)";
    if (!$dsql->ExecuteNoneQuery($sql)) {
        netpay_log('创建订单失败', array('uid' => $uid, 'error' => $dsql->GetError()));
        return array('ok' => false, 'error' => '创建订单失败，请稍后再试');
    }
    $order = netpay_get_order($orderno);
    if (empty($order)) {
        return array('ok' => false, 'error' => '订单创建后读取失败');
    }
    return array('ok' => true, 'error' => '', 'order' => $order);
}

/**
 * 写入非支付类积分变动记录（签到 / 推广链接 / 注册赠送）
 * 统一进入 sea_paylog，供后台与用户中心充值记录查询。
 * @return bool
 */
function netpay_log_points($uid, $rtype, $channel, $points, $remark = '', $uname = '')
{
    global $dsql;
    $uid = intval($uid);
    $rtype = intval($rtype);
    $channel = strtolower((string)$channel);
    $points = intval($points);
    if ($uid <= 0 || $points <= 0) {
        return false;
    }
    if (!in_array($rtype, array(4, 5, 6), true)) {
        return false;
    }
    if (!in_array($channel, array('sign', 'promo', 'reg'), true)) {
        return false;
    }
    $uname = trim((string)$uname);
    if ($uname === '') {
        $row = $dsql->GetOne("select username from sea_member where id={$uid}");
        $uname = is_array($row) && isset($row['username']) ? (string)$row['username'] : '';
    }
    $orderno = netpay_new_order_no();
    $unameEsc = addslashes($uname);
    $channelEsc = addslashes($channel);
    $remarkEsc = addslashes(substr((string)$remark, 0, 255));
    $ip = netpay_client_ip();
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? addslashes(substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255)) : '';
    $now = time();
    $sql = "INSERT INTO sea_paylog
        (orderno,uid,uname,rtype,channel,amount,currency,points,ratio,status,trade_no,card_id,card_key,parent_uid,dist_level,dist_rate,remark,ip,user_agent,ext,addtime,paytime)
        VALUES
        ('" . addslashes($orderno) . "',{$uid},'{$unameEsc}',{$rtype},'{$channelEsc}',0,'POINTS',{$points},0,1,'',0,'',0,0,0,'{$remarkEsc}','{$ip}','{$ua}','',{$now},{$now})";
    if (!$dsql->ExecuteNoneQuery($sql)) {
        netpay_log('积分记录写入失败', array('uid' => $uid, 'rtype' => $rtype, 'channel' => $channel, 'error' => $dsql->GetError()));
        return false;
    }
    return true;
}

/**
 * 管理员修改订单状态（0 待支付 / 1 已完成 / 4 已退款 / 9 未知）
 * 同步调整会员积分，保证仅 status=1 的订单计入统计与用户积分。
 */
function netpay_set_order_status($id, $newStatus, $operator = '')
{
    global $dsql;
    $id = intval($id);
    $newStatus = intval($newStatus);
    if ($id <= 0 || !in_array($newStatus, array(0, 1, 4, 9), true)) {
        return array('ok' => false, 'message' => '参数不合法');
    }
    $order = $dsql->GetOne("SELECT * FROM sea_paylog WHERE id={$id} limit 1");
    if (!is_array($order)) {
        return array('ok' => false, 'message' => '订单不存在');
    }
    $old = intval($order['status']);
    if ($old === $newStatus) {
        return array('ok' => true, 'message' => '订单状态未发生变化');
    }
    $uid = intval($order['uid']);
    $points = intval($order['points']);
    $paytime = intval($order['paytime']);
    if ($newStatus === 1 && $paytime <= 0) {
        $paytime = time();
    }
    $delta = ($newStatus === 1 ? $points : 0) - ($old === 1 ? $points : 0);

    if (!mysqli_begin_transaction($dsql->linkID)) {
        return array('ok' => false, 'message' => '事务开启失败');
    }
    $affected = $dsql->ExecuteNoneQuery2("UPDATE sea_paylog SET status={$newStatus},paytime={$paytime} WHERE id={$id} AND status={$old}");
    if (intval($affected) !== 1) {
        mysqli_rollback($dsql->linkID);
        return array('ok' => false, 'message' => '订单状态已被其他操作修改，请刷新后重试');
    }
    if ($delta !== 0 && $uid > 0) {
        if ($delta > 0) {
            $sql = "UPDATE sea_member SET points=points+{$delta} WHERE id={$uid}";
        } else {
            $abs = abs($delta);
            $sql = "UPDATE sea_member SET points=GREATEST(points-{$abs},0) WHERE id={$uid}";
        }
        if (!$dsql->ExecuteNoneQuery($sql)) {
            mysqli_rollback($dsql->linkID);
            return array('ok' => false, 'message' => '积分同步失败，已回滚');
        }
    }
    mysqli_commit($dsql->linkID);
    netpay_log('管理员修改订单状态', array(
        'id' => $id,
        'orderno' => $order['orderno'],
        'from' => $old,
        'to' => $newStatus,
        'points_delta' => $delta,
        'operator' => (string)$operator,
    ));
    return array('ok' => true, 'message' => '状态已更新为「' . netpay_status_name($newStatus) . '」' . ($delta !== 0 ? '，积分已同步调整' : ''));
}

function netpay_get_order($orderno)
{
    global $dsql;
    $orderno = addslashes((string)$orderno);
    $row = $dsql->GetOne("SELECT * FROM sea_paylog WHERE orderno='{$orderno}' limit 1");
    return is_array($row) ? $row : array();
}

/**
 * 根据支付方式生成支付跳转内容
 * @return array ok/type(redirect|form|html)/url/html/error
 */
function netpay_start_payment($order)
{
    $channel = isset($order['channel']) ? $order['channel'] : '';
    if ($channel === 'alipay') {
        require_once(dirname(__FILE__) . '/alipay.php');
        return netpay_alipay_create($order);
    }
    if ($channel === 'wechat') {
        require_once(dirname(__FILE__) . '/wechat.php');
        return netpay_wechat_create($order);
    }
    if ($channel === 'mazfu') {
        require_once(dirname(__FILE__) . '/mazfu.php');
        return netpay_mazfu_create($order);
    }
    if ($channel === 'usdt') {
        require_once(dirname(__FILE__) . '/usdt.php');
        return netpay_usdt_create($order);
    }
    return array('ok' => false, 'error' => '未知的支付方式');
}

/**
 * 订单到账（幂等）：同一订单重复回调只入账一次
 * @return array ok/already/message/order
 */
function netpay_credit_order($orderno, $tradeNo = '', $paidAmount = null, $payChannel = '')
{
    global $dsql;
    $order = netpay_get_order($orderno);
    if (empty($order)) {
        netpay_log('回调订单不存在', array('orderno' => $orderno));
        return array('ok' => false, 'already' => false, 'message' => '订单不存在');
    }
    if (intval($order['status']) === 1) {
        return array('ok' => true, 'already' => true, 'message' => '订单已处理', 'order' => $order);
    }
    if (intval($order['status']) !== 0) {
        netpay_log('回调订单状态异常', array('orderno' => $orderno, 'status' => $order['status']));
        return array('ok' => false, 'already' => false, 'message' => '订单状态异常', 'order' => $order);
    }
    // 金额校验：第三方回调金额不得小于订单金额
    if ($paidAmount !== null) {
        $paid = round(floatval($paidAmount), 2);
        $expect = round(floatval($order['amount']), 2);
        if ($paid + 0.02 < $expect) {
            netpay_log('回调金额不匹配', array('orderno' => $orderno, 'expect' => $expect, 'paid' => $paid, 'channel' => $payChannel));
            return array('ok' => false, 'already' => false, 'message' => '支付金额不匹配', 'order' => $order);
        }
    }

    $id = intval($order['id']);
    $uid = intval($order['uid']);
    $points = intval($order['points']);
    $now = time();
    $tradeEsc = addslashes(substr((string)$tradeNo, 0, 80));

    $inTransaction = false;
    if (function_exists('mysqli_begin_transaction') && is_object($dsql) && !empty($dsql->linkID)) {
        @mysqli_begin_transaction($dsql->linkID);
        $inTransaction = true;
    }
    $affected = $dsql->ExecuteNoneQuery2("UPDATE sea_paylog SET status=1,paytime={$now},trade_no='{$tradeEsc}' WHERE id={$id} AND status=0");
    if (intval($affected) !== 1) {
        if ($inTransaction) {
            @mysqli_rollback($dsql->linkID);
        }
        $fresh = netpay_get_order($orderno);
        if (!empty($fresh) && intval($fresh['status']) === 1) {
            return array('ok' => true, 'already' => true, 'message' => '订单已处理', 'order' => $fresh);
        }
        netpay_log('订单入账失败：状态更新未生效', array('orderno' => $orderno));
        return array('ok' => false, 'already' => false, 'message' => '订单状态更新失败');
    }
    if ($uid > 0 && $points > 0) {
        $ok = $dsql->ExecuteNoneQuery("UPDATE sea_member SET points=points+{$points} WHERE id={$uid}");
        if (!$ok) {
            if ($inTransaction) {
                @mysqli_rollback($dsql->linkID);
            }
            netpay_log('订单入账失败：积分更新失败', array('orderno' => $orderno, 'uid' => $uid, 'error' => $dsql->GetError()));
            return array('ok' => false, 'already' => false, 'message' => '积分入账失败');
        }
    }
    if ($inTransaction) {
        @mysqli_commit($dsql->linkID);
    }
    netpay_log('订单支付成功', array('orderno' => $orderno, 'uid' => $uid, 'points' => $points, 'channel' => $payChannel, 'trade_no' => $tradeNo));
    $order['status'] = 1;
    $order['paytime'] = $now;
    return array('ok' => true, 'already' => false, 'message' => '入账成功', 'order' => $order);
}

function netpay_close_order($orderno, $remark = '')
{
    global $dsql;
    $orderno = addslashes((string)$orderno);
    $remark = addslashes(substr((string)$remark, 0, 255));
    $dsql->ExecuteNoneQuery("UPDATE sea_paylog SET status=2,remark='{$remark}' WHERE orderno='{$orderno}' AND status=0");
}

/**
 * 充值统计
 * @param int $uid 0 表示全站
 * @param string $extraWhere 内部拼接的附加条件（调用方保证安全）
 */
function netpay_summary($uid = 0, $extraWhere = '')
{
    global $dsql;
    $where = 'status=1';
    $uid = intval($uid);
    if ($uid > 0) {
        $where .= " AND uid={$uid}";
    }
    if ($extraWhere !== '') {
        $where .= ' AND ' . $extraWhere;
    }
    $result = array(
        'card_points' => 0,
        'card_count' => 0,
        'online_cny' => 0,
        'online_usdt' => 0,
        'online_count' => 0,
        'dist_points' => 0,
        'dist_count' => 0,
        'sign_points' => 0,
        'sign_count' => 0,
        'promo_points' => 0,
        'promo_count' => 0,
        'reg_points' => 0,
        'reg_count' => 0,
    );
    $dsql->SetQuery("SELECT rtype,currency,SUM(amount) AS amt,SUM(points) AS pts,COUNT(*) AS cnt FROM sea_paylog WHERE {$where} GROUP BY rtype,currency");
    $dsql->Execute('netpay_sum');
    while ($row = $dsql->GetArray('netpay_sum')) {
        $rtype = intval($row['rtype']);
        $currency = strtoupper($row['currency']);
        $amt = floatval($row['amt']);
        $pts = intval($row['pts']);
        $cnt = intval($row['cnt']);
        if ($rtype === 1) {
            $result['card_points'] += $pts;
            $result['card_count'] += $cnt;
        } elseif ($rtype === 2) {
            if ($currency === 'USDT') {
                $result['online_usdt'] += $amt;
            } else {
                $result['online_cny'] += $amt;
            }
            $result['online_count'] += $cnt;
        } elseif ($rtype === 3) {
            $result['dist_points'] += $pts;
            $result['dist_count'] += $cnt;
        } elseif ($rtype === 4) {
            $result['sign_points'] += $pts;
            $result['sign_count'] += $cnt;
        } elseif ($rtype === 5) {
            $result['promo_points'] += $pts;
            $result['promo_count'] += $cnt;
        } elseif ($rtype === 6) {
            $result['reg_points'] += $pts;
            $result['reg_count'] += $cnt;
        }
    }
    return $result;
}
