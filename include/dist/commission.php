<?php
/**
 * 三级分销 - 佣金生成、结算与退款冲正
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

function dist_tx_begin()
{
    global $dsql;
    if (function_exists('mysqli_begin_transaction') && is_object($dsql) && !empty($dsql->linkID)) {
        @mysqli_begin_transaction($dsql->linkID);
        return true;
    }
    return false;
}

function dist_tx_commit($inTx)
{
    global $dsql;
    if ($inTx) {
        @mysqli_commit($dsql->linkID);
    }
}

function dist_tx_rollback($inTx)
{
    global $dsql;
    if ($inTx) {
        @mysqli_rollback($dsql->linkID);
    }
}

/**
 * 消费成功生成佣金（幂等）
 * @param int $buyerUid 购买者
 * @param int $points 消耗积分
 * @param int $tradeType 1视频 2会员 3充值 4充值卡
 * @param int $scopeId 视频ID/会员组ID
 * @param string $orderNo 唯一订单号，不传则自动生成
 * @return array ok/message/order_no
 */
function dist_commission_on_consume($buyerUid, $points, $tradeType = 1, $scopeId = 0, $orderNo = '')
{
    global $dsql;
    $buyerUid = intval($buyerUid);
    $points = intval($points);
    $tradeType = intval($tradeType);
    $scopeId = intval($scopeId);
    if (!dist_enabled()) {
        return array('ok' => false, 'message' => '分销功能未开启');
    }
    if ($buyerUid <= 0 || $points <= 0) {
        return array('ok' => false, 'message' => '参数不完整');
    }
    if (in_array($tradeType, array(3, 4), true) && intval(dist_cfg('recharge_commission', 0)) !== 1) {
        return array('ok' => false, 'message' => '充值不计佣');
    }
    if ($orderNo === '') {
        $orderNo = dist_new_no('D');
    }
    $orderNo = substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$orderNo), 0, 64);
    if ($orderNo === '') {
        return array('ok' => false, 'message' => '订单号无效');
    }
    $baseCents = dist_points_to_cents($points);
    if ($baseCents <= 0) {
        return array('ok' => false, 'message' => '计佣基数为 0');
    }
    $risk = dist_risk_assess($buyerUid, $baseCents, $tradeType);
    if (empty($risk['ok'])) {
        return array('ok' => false, 'message' => '风控拦截');
    }
    $chain = dist_ancestor_chain($buyerUid);
    $rateInfo = dist_calc_rates($chain, $tradeType, $scopeId);
    $levels = $rateInfo['levels'];
    $now = time();
    $settleTime = $now + intval($risk['observe_days']) * 86400;
    $snapshot = array();
    foreach ($levels as $lv) {
        $snapshot[] = array(
            'uid' => $lv['uid'],
            'level' => $lv['level'],
            'rate' => dist_rate_text($lv['rate_bp']),
            'grade' => $lv['grade']
        );
    }
    $snapJson = addslashes(json_encode($snapshot, JSON_UNESCAPED_UNICODE));
    $baseStr = dist_money($baseCents);
    $orderStatus = empty($levels) ? 1 : 0;
    $ruleId = intval($rateInfo['rule_id']);
    $ruleVersion = intval($rateInfo['rule_version']);
    $orderEsc = addslashes($orderNo);

    $inTx = dist_tx_begin();
    $sql = "INSERT IGNORE INTO sea_dist_order (orderno,buyer_uid,trade_type,base_amount,currency,chain_snapshot,rule_id,rule_version,status,refund_amount,pay_time,settle_time,create_time,update_time)
        VALUES ('{$orderEsc}',{$buyerUid},{$tradeType},{$baseStr},'POINTS','{$snapJson}',{$ruleId},{$ruleVersion},{$orderStatus},0,{$now},{$settleTime},{$now},{$now})";
    $inserted = intval($dsql->ExecuteNoneQuery2($sql));
    if ($inserted !== 1) {
        dist_tx_commit($inTx);
        return array('ok' => true, 'already' => true, 'message' => '订单已计佣', 'order_no' => $orderNo);
    }
    $created = 0;
    $maxPerOrder = intval(dist_cfg('max_commission_order', 5000)) * 100;
    foreach ($levels as $lv) {
        $cents = intdiv($baseCents * intval($lv['rate_bp']), 10000);
        if ($cents < 1) {
            continue;
        }
        $remark = '';
        if ($maxPerOrder > 0 && $cents > $maxPerOrder) {
            $cents = $maxPerOrder;
            $remark = '触发单笔佣金上限';
        }
        $capInfo = dist_risk_beneficiary_cap($lv['uid'], $cents, $settleTime);
        if (!empty($capInfo['remark'])) {
            $remark = trim($remark . '；' . $capInfo['remark'], '；');
        }
        $lvSettle = intval($capInfo['settle_time']);
        if ($lvSettle < $settleTime) {
            $lvSettle = $settleTime;
        }
        $uid = intval($lv['uid']);
        $level = intval($lv['level']);
        $rate = dist_rate_text($lv['rate_bp']);
        $amountStr = dist_money($cents);
        $remarkEsc = addslashes($remark);
        $sql = "INSERT IGNORE INTO sea_dist_commission (order_no,buyer_uid,uid,level,base_amount,rate,amount,status,settle_time,create_time,update_time,remark)
            VALUES ('{$orderEsc}',{$buyerUid},{$uid},{$level},{$baseStr},{$rate},{$amountStr},0,{$lvSettle},{$now},{$now},'{$remarkEsc}')";
        if (intval($dsql->ExecuteNoneQuery2($sql)) === 1) {
            dist_account_add_pending($uid, $cents);
            $created++;
        }
    }
    dist_tx_commit($inTx);
    dist_log($buyerUid, 'commission_adjust', 'order', $orderNo, null, array(
        'base' => $baseStr,
        'levels' => $snapshot,
        'created' => $created,
        'risk' => $risk['flags']
    ), 3, 0, '消费计佣');
    return array('ok' => true, 'already' => false, 'message' => '计佣完成', 'order_no' => $orderNo, 'count' => $created);
}

/**
 * 订单结算状态刷新
 */
function dist_order_refresh($orderNo)
{
    global $dsql;
    $orderEsc = addslashes((string)$orderNo);
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_commission WHERE order_no='{$orderEsc}' AND status=0");
    if (is_array($row) && intval($row['dd']) === 0) {
        $dsql->ExecuteNoneQuery("UPDATE sea_dist_order SET status=1,settle_time=" . time() . ",update_time=" . time() . " WHERE orderno='{$orderEsc}' AND status=0");
    }
}

/**
 * 批量结算到期佣金
 * @return array settled/failed
 */
function dist_settle_due($limit = 0)
{
    global $dsql;
    $limit = intval($limit);
    if ($limit <= 0) {
        $limit = intval(dist_cfg('settle_batch', 200));
    }
    $limit = max(1, min($limit, 2000));
    $now = time();
    $rows = array();
    $dsql->SetQuery("SELECT id,uid,amount,order_no FROM sea_dist_commission WHERE status=0 AND settle_time>0 AND settle_time<={$now} ORDER BY id ASC limit {$limit}");
    $dsql->Execute('dist_settle');
    while ($row = $dsql->GetArray('dist_settle')) {
        $rows[] = $row;
    }
    $settled = 0;
    $failed = 0;
    $orders = array();
    foreach ($rows as $row) {
        $id = intval($row['id']);
        $uid = intval($row['uid']);
        $cents = intval(round(floatval($row['amount']) * 100));
        $inTx = dist_tx_begin();
        $aff = intval($dsql->ExecuteNoneQuery2("UPDATE sea_dist_commission SET status=1,update_time={$now} WHERE id={$id} AND status=0"));
        if ($aff !== 1) {
            dist_tx_rollback($inTx);
            continue;
        }
        if (!dist_account_settle($uid, $cents)) {
            dist_tx_rollback($inTx);
            $failed++;
            dist_log($uid, 'commission_adjust', 'commission', (string)$id, null, null, 3, 0, '结算失败：账户待结算余额不足');
            continue;
        }
        dist_tx_commit($inTx);
        $settled++;
        $orders[$row['order_no']] = 1;
    }
    foreach (array_keys($orders) as $ono) {
        dist_order_refresh($ono);
    }
    return array('settled' => $settled, 'failed' => $failed);
}

/**
 * 管理员手动放行单笔佣金
 */
function dist_settle_one($id, $operator = '')
{
    global $dsql;
    $id = intval($id);
    $row = $dsql->GetOne("SELECT * FROM sea_dist_commission WHERE id={$id} limit 1");
    if (!is_array($row)) {
        return array('ok' => false, 'message' => '佣金记录不存在');
    }
    if (intval($row['status']) !== 0) {
        return array('ok' => false, 'message' => '当前状态不可放行');
    }
    $now = time();
    $uid = intval($row['uid']);
    $cents = intval(round(floatval($row['amount']) * 100));
    $inTx = dist_tx_begin();
    $aff = intval($dsql->ExecuteNoneQuery2("UPDATE sea_dist_commission SET status=1,update_time={$now} WHERE id={$id} AND status=0"));
    if ($aff !== 1 || !dist_account_settle($uid, $cents)) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '放行失败，账户余额不足或状态已变更');
    }
    dist_tx_commit($inTx);
    dist_order_refresh($row['order_no']);
    dist_log($uid, 'commission_adjust', 'commission', (string)$id, array('status' => 0), array('status' => 1), 2, 0, '人工放行' . ($operator !== '' ? '：' . $operator : ''));
    return array('ok' => true, 'message' => '已放行为可提现');
}

/**
 * 管理员作废佣金
 */
function dist_commission_void($id, $operator = '', $reason = '')
{
    global $dsql;
    $id = intval($id);
    $row = $dsql->GetOne("SELECT * FROM sea_dist_commission WHERE id={$id} limit 1");
    if (!is_array($row)) {
        return array('ok' => false, 'message' => '佣金记录不存在');
    }
    $status = intval($row['status']);
    if (in_array($status, array(4, 5), true)) {
        return array('ok' => false, 'message' => '该佣金已取消或冲正');
    }
    $now = time();
    $uid = intval($row['uid']);
    $cents = intval(round(floatval($row['amount']) * 100));
    $inTx = dist_tx_begin();
    $aff = intval($dsql->ExecuteNoneQuery2("UPDATE sea_dist_commission SET status=4,update_time={$now},remark=CONCAT(remark,' | 人工作废') WHERE id={$id} AND status={$status}"));
    if ($aff !== 1) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '状态已变更，请刷新');
    }
    dist_account_reversal($uid, $status, $cents);
    dist_tx_commit($inTx);
    dist_order_refresh($row['order_no']);
    dist_log($uid, 'commission_adjust', 'commission', (string)$id, array('status' => $status), array('status' => 4), 2, 0, '人工作废：' . $reason . ($operator !== '' ? '（' . $operator . '）' : ''));
    return array('ok' => true, 'message' => '佣金已作废');
}

/**
 * 提现/兑换成功后，将对应金额的“可提现”佣金按先进先出标记为“已发放”
 * 说明：佣金表唯一键为 (order_no,uid)，无法按金额拆分单行，故按行整笔标记，
 * 累计标记金额达到本次提现金额即停止。
 * @return array ok/paid/rows
 */
function dist_commission_mark_paid($uid, $amountCents)
{
    global $dsql;
    $uid = intval($uid);
    $amountCents = intval($amountCents);
    if ($uid <= 0 || $amountCents <= 0) {
        return array('ok' => false, 'paid' => 0, 'rows' => 0);
    }
    $rows = array();
    $dsql->SetQuery("SELECT id,amount FROM sea_dist_commission WHERE uid={$uid} AND status=1 ORDER BY settle_time ASC,id ASC");
    $dsql->Execute('dist_mark_paid');
    while ($r = $dsql->GetArray('dist_mark_paid')) {
        $rows[] = $r;
    }
    $now = time();
    $paid = 0;
    $count = 0;
    foreach ($rows as $r) {
        if ($paid >= $amountCents) {
            break;
        }
        $cents = intval(round(floatval($r['amount']) * 100));
        if ($cents <= 0) {
            continue;
        }
        $id = intval($r['id']);
        $aff = intval($dsql->ExecuteNoneQuery2("UPDATE sea_dist_commission SET status=3,update_time={$now},remark=CONCAT(COALESCE(remark,''),' | 提现已发放') WHERE id={$id} AND status=1"));
        if ($aff === 1) {
            $paid += $cents;
            $count++;
        }
    }
    return array('ok' => true, 'paid' => $paid, 'rows' => $count);
}

/**
 * 提现回退：将对应金额的“已发放”佣金按最近发放优先恢复为“可提现”
 * @return array ok/restored/rows
 */
function dist_commission_mark_unpaid($uid, $amountCents)
{
    global $dsql;
    $uid = intval($uid);
    $amountCents = intval($amountCents);
    if ($uid <= 0 || $amountCents <= 0) {
        return array('ok' => false, 'restored' => 0, 'rows' => 0);
    }
    $rows = array();
    $dsql->SetQuery("SELECT id,amount FROM sea_dist_commission WHERE uid={$uid} AND status=3 ORDER BY update_time DESC,id DESC");
    $dsql->Execute('dist_mark_unpaid');
    while ($r = $dsql->GetArray('dist_mark_unpaid')) {
        $rows[] = $r;
    }
    $now = time();
    $restored = 0;
    $count = 0;
    foreach ($rows as $r) {
        if ($restored >= $amountCents) {
            break;
        }
        $cents = intval(round(floatval($r['amount']) * 100));
        if ($cents <= 0) {
            continue;
        }
        $id = intval($r['id']);
        $aff = intval($dsql->ExecuteNoneQuery2("UPDATE sea_dist_commission SET status=1,update_time={$now},remark=CONCAT(COALESCE(remark,''),' | 提现回退') WHERE id={$id} AND status=3"));
        if ($aff === 1) {
            $restored += $cents;
            $count++;
        }
    }
    return array('ok' => true, 'restored' => $restored, 'rows' => $count);
}

/**
 * 退款冲正
 * @param string $orderNo 关联订单号
 * @param float|null $refundYuan 本次退款金额（元），null 表示剩余全额
 * @return array ok/message
 */
function dist_refund_order($orderNo, $refundYuan = null)
{
    global $dsql;
    $orderEsc = addslashes((string)$orderNo);
    $order = $dsql->GetOne("SELECT * FROM sea_dist_order WHERE orderno='{$orderEsc}' limit 1");
    if (!is_array($order)) {
        return array('ok' => true, 'message' => '无分销订单，无需冲正');
    }
    $baseCents = intval(round(floatval($order['base_amount']) * 100));
    $refunded = intval(round(floatval($order['refund_amount']) * 100));
    $remaining = $baseCents - $refunded;
    if ($remaining <= 0) {
        return array('ok' => true, 'message' => '订单已全额冲正');
    }
    if ($refundYuan === null) {
        $refundCents = $remaining;
    } else {
        $refundCents = intval(round(floatval($refundYuan) * 100));
        if ($refundCents <= 0) {
            return array('ok' => false, 'message' => '退款金额无效');
        }
        if ($refundCents > $remaining) {
            $refundCents = $remaining;
        }
    }
    $full = $refundCents >= $remaining;
    $now = time();
    $refundedStr = dist_money($refunded);
    $newRefundStr = dist_money($refunded + $refundCents);
    $newStatus = $full ? 3 : 2;
    $inTx = dist_tx_begin();
    $aff = intval($dsql->ExecuteNoneQuery2("UPDATE sea_dist_order SET refund_amount={$newRefundStr},status={$newStatus},update_time={$now} WHERE id=" . intval($order['id']) . " AND refund_amount={$refundedStr}"));
    if ($aff !== 1) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '订单退款状态已变更，请刷新');
    }
    $rows = array();
    $dsql->SetQuery("SELECT * FROM sea_dist_commission WHERE order_no='{$orderEsc}' AND status IN (0,1,2,3)");
    $dsql->Execute('dist_refund');
    while ($r = $dsql->GetArray('dist_refund')) {
        $rows[] = $r;
    }
    $total = 0;
    foreach ($rows as $r) {
        $current = intval(round(floatval($r['amount']) * 100));
        if ($current <= 0) {
            continue;
        }
        $delta = $full ? $current : intdiv($current * $refundCents, max(1, $remaining));
        if ($delta <= 0) {
            continue;
        }
        if ($delta > $current) {
            $delta = $current;
        }
        $newAmount = $current - $delta;
        $status = intval($r['status']);
        $newRowStatus = $status;
        if ($newAmount === 0) {
            $newRowStatus = $status === 0 ? 4 : 5;
        }
        dist_account_reversal(intval($r['uid']), $status, $delta);
        $dsql->ExecuteNoneQuery("UPDATE sea_dist_commission SET amount=" . dist_money($newAmount) . ",status={$newRowStatus},update_time={$now},remark=CONCAT(remark,' | 退款冲正 " . dist_money($delta) . " 元') WHERE id=" . intval($r['id']));
        $total += $delta;
    }
    dist_tx_commit($inTx);
    dist_log(intval($order['buyer_uid']), 'commission_adjust', 'order', $orderNo, array('refund' => $refundedStr), array('refund' => $newRefundStr, 'reversed' => dist_money($total)), 3, 0, '订单退款冲正');
    return array('ok' => true, 'message' => '冲正完成，冲正金额 ' . dist_money($total) . ' 元');
}
