<?php
/**
 * 三级分销 - 提现申请与审核
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

/**
 * 申请提现
 * @param int $amountCents 申请金额（分）
 * @return array ok/message
 */
function dist_withdraw_apply($uid, $amountCents, $channel = 'manual', $accountInfo = '')
{
    global $dsql;
    $uid = intval($uid);
    $amountCents = intval($amountCents);
    $channel = preg_replace('/[^a-z]/', '', strtolower((string)$channel));
    if (!in_array($channel, array('alipay', 'wechat', 'usdt', 'bank', 'manual'), true)) {
        $channel = 'manual';
    }
    $accountInfo = trim((string)$accountInfo);
    if ($accountInfo === '' || mb_strlen($accountInfo, 'UTF-8') > 200) {
        return array('ok' => false, 'message' => '请填写正确的收款账户信息');
    }
    if ($amountCents <= 0) {
        return array('ok' => false, 'message' => '提现金额不正确');
    }
    $user = dist_user_get($uid);
    if (empty($user) || intval($user['status']) !== 1) {
        return array('ok' => false, 'message' => '分销身份不可用');
    }
    $min = intval(dist_cfg('min_withdraw', 100)) * 100;
    $max = intval(dist_cfg('max_withdraw', 50000)) * 100;
    if ($amountCents < $min) {
        return array('ok' => false, 'message' => '最低提现金额为 ' . dist_money($min) . ' 元');
    }
    if ($max > 0 && $amountCents > $max) {
        return array('ok' => false, 'message' => '单笔提现不得超过 ' . dist_money($max) . ' 元');
    }
    $acct = dist_account_get($uid);
    if (empty($acct)) {
        // 早期开通的分销员可能没有账户记录，先补建再校验余额
        dist_account_ensure($uid);
        $acct = dist_account_get($uid);
    }
    if (empty($acct)) {
        return array('ok' => false, 'message' => '分销账户不存在');
    }
    if (intval(round(floatval($acct['negative_amount']) * 100)) > 0) {
        return array('ok' => false, 'message' => '存在负余额，请先抵扣后再提现');
    }
    $available = intval(round(floatval($acct['available_amount']) * 100));
    if ($available < $amountCents) {
        return array('ok' => false, 'message' => '可提现余额不足');
    }
    $dailyLimit = intval(dist_cfg('withdraw_daily_limit', 3));
    if ($dailyLimit > 0) {
        $dayStart = strtotime(date('Y-m-d') . ' 00:00:00');
        $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_withdraw WHERE uid={$uid} AND create_time>={$dayStart} AND status NOT IN (4,6)");
        if (is_array($row) && intval($row['dd']) >= $dailyLimit) {
            return array('ok' => false, 'message' => '今日提现申请次数已达上限');
        }
    }
    $feeRate = floatval(dist_cfg('withdraw_fee', 0));
    $feeCents = $feeRate > 0 ? intval(round($amountCents * $feeRate)) : 0;
    if ($feeCents >= $amountCents) {
        return array('ok' => false, 'message' => '手续费配置异常');
    }
    $realCents = $amountCents - $feeCents;
    $withdrawNo = dist_new_no('W');
    $amountStr = dist_money($amountCents);
    $feeStr = dist_money($feeCents);
    $realStr = dist_money($realCents);
    $channelEsc = addslashes($channel);
    $accountEsc = addslashes($accountInfo);
    $noEsc = addslashes($withdrawNo);
    $ip = addslashes(dist_client_ip());
    $now = time();

    $inTx = dist_tx_begin();
    if (!dist_account_freeze($uid, $amountCents)) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '可提现余额不足或账户状态已变更');
    }
    $sql = "INSERT INTO sea_dist_withdraw (withdraw_no,uid,amount,fee,real_amount,channel,account_info,status,audit_admin,audit_time,audit_remark,pay_admin,pay_time,pay_trade_no,voucher,ip,create_time,update_time)
        VALUES ('{$noEsc}',{$uid},{$amountStr},{$feeStr},{$realStr},'{$channelEsc}','{$accountEsc}',0,'',0,'','',0,'','','{$ip}',{$now},{$now})";
    if (!$dsql->ExecuteNoneQuery($sql)) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '提现单创建失败，请稍后再试');
    }
    dist_tx_commit($inTx);
    dist_log($uid, 'withdraw_audit', 'withdraw', $withdrawNo, null, array('amount' => $amountStr, 'status' => 0), 1, $uid, '提交提现申请');
    return array('ok' => true, 'message' => '提现申请已提交，等待审核', 'withdraw_no' => $withdrawNo);
}

/**
 * 已打款提现回退：已提现金额回到可提现，并恢复对应佣金为可提现
 */
function dist_withdraw_refund_paid($uid, $cents)
{
    if (!dist_account_reverse_withdraw($uid, $cents)) {
        return false;
    }
    dist_commission_mark_unpaid($uid, $cents);
    return true;
}

/**
 * 提现单对应资金当前位置：0/1/2 冻结中，3 已提现，4/5/6 已退回可提现
 */
function dist_withdraw_funds_position($status)
{
    $status = intval($status);
    if (in_array($status, array(0, 1, 2), true)) {
        return 'frozen';
    }
    if ($status === 3) {
        return 'withdrawn';
    }
    return 'available';
}

/**
 * 将提现单对应资金调整到目标位置
 * @param string $target frozen/available/withdrawn
 */
function dist_withdraw_move_funds($uid, $cents, $status, $target)
{
    $position = dist_withdraw_funds_position($status);
    if ($position === $target) {
        return true;
    }
    if ($target === 'available') {
        return $position === 'frozen' ? dist_account_unfreeze($uid, $cents) : dist_withdraw_refund_paid($uid, $cents);
    }
    if ($target === 'withdrawn') {
        if ($position === 'available') {
            if (!dist_account_freeze($uid, $cents)) {
                return false;
            }
            if (!dist_account_finish_withdraw($uid, $cents)) {
                dist_account_unfreeze($uid, $cents);
                return false;
            }
        } elseif (!dist_account_finish_withdraw($uid, $cents)) {
            return false;
        }
        dist_commission_mark_paid($uid, $cents);
        return true;
    }
    // 目标为冻结中
    if ($position === 'withdrawn') {
        if (!dist_withdraw_refund_paid($uid, $cents)) {
            return false;
        }
    }
    return dist_account_freeze($uid, $cents);
}

/**
 * 状态未变化时返回 true（用于重复提交相同内容导致影响行数为 0 的情况）
 */
function dist_withdraw_status_unchanged($id, $status)
{
    global $dsql;
    $cur = $dsql->GetOne("SELECT status FROM sea_dist_withdraw WHERE id=" . intval($id) . " limit 1");
    return is_array($cur) && intval($cur['status']) === intval($status);
}

/**
 * 审核提现
 */
function dist_withdraw_audit($id, $approve, $admin = '', $remark = '')
{
    global $dsql;
    $id = intval($id);
    $row = $dsql->GetOne("SELECT * FROM sea_dist_withdraw WHERE id={$id} limit 1");
    if (!is_array($row)) {
        return array('ok' => false, 'message' => '提现单不存在');
    }
    $status = intval($row['status']);
    $uid = intval($row['uid']);
    $cents = intval(round(floatval($row['amount']) * 100));
    $newStatus = $approve ? ($status === 3 ? 3 : 1) : 4;
    $target = $approve ? 'frozen' : 'available';
    if ($status === 3) {
        // 已打款：通过为复核确认，不改变资金；驳回则退回
        $target = $approve ? 'withdrawn' : 'available';
    }
    $now = time();
    $adminEsc = addslashes(substr((string)$admin, 0, 32));
    $remarkEsc = addslashes(substr((string)$remark, 0, 255));
    $inTx = dist_tx_begin();
    $aff = intval($dsql->ExecuteNoneQuery2("UPDATE sea_dist_withdraw SET status={$newStatus},audit_admin='{$adminEsc}',audit_time={$now},audit_remark='{$remarkEsc}',update_time={$now} WHERE id={$id} AND status={$status}"));
    if ($aff !== 1 && !dist_withdraw_status_unchanged($id, $status)) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '状态已变更，请刷新');
    }
    if (!dist_withdraw_move_funds($uid, $cents, $status, $target)) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '审核失败：可提现余额不足或资金处理异常');
    }
    dist_tx_commit($inTx);
    $logMsg = '审核驳回';
    if ($approve) {
        $logMsg = $status === 3 ? '已打款复核通过' : '审核通过';
    }
    dist_log($uid, 'withdraw_audit', 'withdraw', (string)$row['withdraw_no'], array('status' => $status), array('status' => $newStatus), 2, 0, $logMsg . ($remark !== '' ? '：' . $remark : ''));
    if ($approve) {
        return array('ok' => true, 'message' => $status === 3 ? '已复核确认' : '已审核通过');
    }
    return array('ok' => true, 'message' => '已驳回');
}

/**
 * 确认打款
 */
function dist_withdraw_pay($id, $admin = '', $tradeNo = '', $voucher = '')
{
    global $dsql;
    $id = intval($id);
    $row = $dsql->GetOne("SELECT * FROM sea_dist_withdraw WHERE id={$id} limit 1");
    if (!is_array($row)) {
        return array('ok' => false, 'message' => '提现单不存在');
    }
    $status = intval($row['status']);
    $uid = intval($row['uid']);
    $cents = intval(round(floatval($row['amount']) * 100));
    $now = time();
    $adminEsc = addslashes(substr((string)$admin, 0, 32));
    $tradeEsc = addslashes(substr((string)$tradeNo, 0, 80));
    $voucherEsc = addslashes(substr((string)$voucher, 0, 255));
    $inTx = dist_tx_begin();
    $aff = intval($dsql->ExecuteNoneQuery2("UPDATE sea_dist_withdraw SET status=3,pay_admin='{$adminEsc}',pay_time={$now},pay_trade_no='{$tradeEsc}',voucher='{$voucherEsc}',update_time={$now} WHERE id={$id} AND status={$status}"));
    if ($aff !== 1 && !dist_withdraw_status_unchanged($id, $status)) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '状态已变更，请刷新');
    }
    if (!dist_withdraw_move_funds($uid, $cents, $status, 'withdrawn')) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '打款失败：可提现余额不足或资金处理异常');
    }
    dist_tx_commit($inTx);
    dist_log($uid, 'withdraw_audit', 'withdraw', (string)$row['withdraw_no'], array('status' => $status), array('status' => 3), 2, 0, '确认打款');
    return array('ok' => true, 'message' => '已标记为已打款');
}

/**
 * 撤销提现（用户或管理员）
 */
function dist_withdraw_cancel($id, $uid = 0, $admin = '')
{
    global $dsql;
    $id = intval($id);
    $row = $dsql->GetOne("SELECT * FROM sea_dist_withdraw WHERE id={$id} limit 1");
    if (!is_array($row)) {
        return array('ok' => false, 'message' => '提现单不存在');
    }
    $status = intval($row['status']);
    $isAdmin = intval($uid) <= 0;
    if (!$isAdmin) {
        if ($status !== 0) {
            return array('ok' => false, 'message' => '仅待审核的提现单可以撤销');
        }
        if (intval($row['uid']) !== intval($uid)) {
            return array('ok' => false, 'message' => '无权操作');
        }
    }
    $owner = intval($row['uid']);
    $cents = intval(round(floatval($row['amount']) * 100));
    $now = time();
    $inTx = dist_tx_begin();
    $aff = intval($dsql->ExecuteNoneQuery2("UPDATE sea_dist_withdraw SET status=6,update_time={$now} WHERE id={$id} AND status={$status}"));
    if ($aff !== 1 && !dist_withdraw_status_unchanged($id, $status)) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '撤销失败，请刷新后重试');
    }
    if (!dist_withdraw_move_funds($owner, $cents, $status, 'available')) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '撤销失败：资金处理异常');
    }
    dist_tx_commit($inTx);
    dist_log($owner, 'withdraw_audit', 'withdraw', (string)$row['withdraw_no'], array('status' => $status), array('status' => 6), $isAdmin ? 2 : 1, 0, '撤销提现');
    return array('ok' => true, 'message' => '提现申请已撤销');
}

/**
 * 打款失败处理：金额回到可提现
 */
function dist_withdraw_fail($id, $admin = '', $remark = '')
{
    global $dsql;
    $id = intval($id);
    $row = $dsql->GetOne("SELECT * FROM sea_dist_withdraw WHERE id={$id} limit 1");
    if (!is_array($row)) {
        return array('ok' => false, 'message' => '提现单不存在');
    }
    $status = intval($row['status']);
    $uid = intval($row['uid']);
    $cents = intval(round(floatval($row['amount']) * 100));
    $now = time();
    $remarkEsc = addslashes(substr((string)$remark, 0, 255));
    $inTx = dist_tx_begin();
    $aff = intval($dsql->ExecuteNoneQuery2("UPDATE sea_dist_withdraw SET status=5,audit_remark='{$remarkEsc}',update_time={$now} WHERE id={$id} AND status={$status}"));
    if ($aff !== 1 && !dist_withdraw_status_unchanged($id, $status)) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '操作失败，请刷新后重试');
    }
    if (!dist_withdraw_move_funds($uid, $cents, $status, 'available')) {
        dist_tx_rollback($inTx);
        return array('ok' => false, 'message' => '操作失败：资金处理异常');
    }
    dist_tx_commit($inTx);
    dist_log($uid, 'withdraw_audit', 'withdraw', (string)$row['withdraw_no'], array('status' => $status), array('status' => 5), 2, 0, '打款失败：' . $remark);
    return array('ok' => true, 'message' => '已标记打款失败，金额已退回');
}
