<?php
/**
 * 三级分销 - 分销账户与积分兑换
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

function dist_account_ensure($uid)
{
    global $dsql;
    $uid = intval($uid);
    if ($uid <= 0) {
        return false;
    }
    $sql = "INSERT IGNORE INTO sea_dist_account (uid,total_amount,pending_amount,available_amount,frozen_amount,withdrawn_amount,negative_amount,points_converted,version,update_time)
        VALUES ({$uid},0,0,0,0,0,0,0,0," . time() . ")";
    return $dsql->ExecuteNoneQuery($sql);
}

function dist_account_get($uid)
{
    global $dsql;
    $uid = intval($uid);
    if ($uid <= 0) {
        return array();
    }
    $row = $dsql->GetOne("SELECT * FROM sea_dist_account WHERE uid={$uid} limit 1");
    return is_array($row) ? $row : array();
}

function dist_account_add_pending($uid, $cents)
{
    global $dsql;
    $cents = intval($cents);
    if ($cents <= 0) {
        return true;
    }
    dist_account_ensure($uid);
    $m = dist_money($cents);
    $now = time();
    return $dsql->ExecuteNoneQuery("UPDATE sea_dist_account SET total_amount=total_amount+{$m},pending_amount=pending_amount+{$m},version=version+1,update_time={$now} WHERE uid=" . intval($uid));
}

/**
 * 待结算 -> 可提现（条件更新，幂等由调用方保证）
 */
function dist_account_settle($uid, $cents)
{
    global $dsql;
    $cents = intval($cents);
    if ($cents <= 0) {
        return true;
    }
    $m = dist_money($cents);
    $now = time();
    $sql = "UPDATE sea_dist_account SET pending_amount=pending_amount-{$m},available_amount=available_amount+{$m},version=version+1,update_time={$now}
        WHERE uid=" . intval($uid) . " AND pending_amount>={$m}";
    return intval($dsql->ExecuteNoneQuery2($sql)) === 1;
}

/**
 * 可提现 -> 提现冻结
 */
function dist_account_freeze($uid, $cents)
{
    global $dsql;
    $cents = intval($cents);
    if ($cents <= 0) {
        return false;
    }
    $m = dist_money($cents);
    $now = time();
    $sql = "UPDATE sea_dist_account SET available_amount=available_amount-{$m},frozen_amount=frozen_amount+{$m},version=version+1,update_time={$now}
        WHERE uid=" . intval($uid) . " AND available_amount>={$m}";
    return intval($dsql->ExecuteNoneQuery2($sql)) === 1;
}

/**
 * 提现驳回/撤销：冻结 -> 可提现
 */
function dist_account_unfreeze($uid, $cents)
{
    global $dsql;
    $cents = intval($cents);
    if ($cents <= 0) {
        return false;
    }
    $m = dist_money($cents);
    $now = time();
    $sql = "UPDATE sea_dist_account SET frozen_amount=frozen_amount-{$m},available_amount=available_amount+{$m},version=version+1,update_time={$now}
        WHERE uid=" . intval($uid) . " AND frozen_amount>={$m}";
    return intval($dsql->ExecuteNoneQuery2($sql)) === 1;
}

/**
 * 提现成功：冻结 -> 已提现
 */
function dist_account_finish_withdraw($uid, $cents)
{
    global $dsql;
    $cents = intval($cents);
    if ($cents <= 0) {
        return false;
    }
    $m = dist_money($cents);
    $now = time();
    $sql = "UPDATE sea_dist_account SET frozen_amount=frozen_amount-{$m},withdrawn_amount=withdrawn_amount+{$m},version=version+1,update_time={$now}
        WHERE uid=" . intval($uid) . " AND frozen_amount>={$m}";
    return intval($dsql->ExecuteNoneQuery2($sql)) === 1;
}

/**
 * 已打款提现回退：已提现 -> 可提现
 */
function dist_account_reverse_withdraw($uid, $cents)
{
    global $dsql;
    $cents = intval($cents);
    if ($cents <= 0) {
        return false;
    }
    $m = dist_money($cents);
    $now = time();
    $sql = "UPDATE sea_dist_account SET withdrawn_amount=withdrawn_amount-{$m},available_amount=available_amount+{$m},version=version+1,update_time={$now}
        WHERE uid=" . intval($uid) . " AND withdrawn_amount>={$m}";
    return intval($dsql->ExecuteNoneQuery2($sql)) === 1;
}

/**
 * 退款冲正：按佣金原状态扣减对应余额
 * @param int $origStatus 佣金原状态 0/1/2/3
 */function dist_account_reversal($uid, $origStatus, $cents)
{
    global $dsql;
    $uid = intval($uid);
    $cents = intval($cents);
    $origStatus = intval($origStatus);
    if ($uid <= 0 || $cents <= 0) {
        return true;
    }
    $now = time();
    $d = dist_money($cents);
    $total = "total_amount=GREATEST(total_amount-{$d},0)";
    if ($origStatus === 0) {
        $set = "pending_amount=GREATEST(pending_amount-{$d},0)";
    } elseif ($origStatus === 1) {
        $acct = dist_account_get($uid);
        $avail = is_array($acct) ? intval(round(floatval($acct['available_amount']) * 100)) : 0;
        if ($avail >= $cents) {
            $set = "available_amount=available_amount-{$d}";
        } else {
            $short = dist_money($cents - $avail);
            $set = "available_amount=0,negative_amount=negative_amount+{$short}";
        }
    } elseif ($origStatus === 2) {
        $acct = dist_account_get($uid);
        $frozen = is_array($acct) ? intval(round(floatval($acct['frozen_amount']) * 100)) : 0;
        if ($frozen >= $cents) {
            $set = "frozen_amount=frozen_amount-{$d}";
        } else {
            $short = dist_money($cents - $frozen);
            $set = "frozen_amount=0,negative_amount=negative_amount+{$short}";
        }
    } else {
        $set = "negative_amount=negative_amount+{$d}";
    }
    $sql = "UPDATE sea_dist_account SET {$total},{$set},version=version+1,update_time={$now} WHERE uid={$uid}";
    return $dsql->ExecuteNoneQuery($sql);
}

/**
 * 可提现余额转为积分
 * @return array ok/message/points
 */
function dist_convert_points($uid, $cents)
{
    global $dsql;
    $uid = intval($uid);
    $cents = intval($cents);
    if ($uid <= 0 || $cents <= 0) {
        return array('ok' => false, 'message' => '金额不正确');
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
        return array('ok' => false, 'message' => '存在负余额，暂不可转积分');
    }
    $points = dist_cents_to_points($cents);
    if ($points <= 0) {
        return array('ok' => false, 'message' => '换算积分无效');
    }
    $m = dist_money($cents);
    $now = time();
    $sql = "UPDATE sea_dist_account SET available_amount=available_amount-{$m},points_converted=points_converted+{$m},version=version+1,update_time={$now}
        WHERE uid={$uid} AND available_amount>={$m}";
    if (intval($dsql->ExecuteNoneQuery2($sql)) !== 1) {
        return array('ok' => false, 'message' => '可提现余额不足');
    }
    $orderno = dist_new_no('FX');
    $remark = '分销佣金转积分，佣金金额 ' . $m . ' 元';
    $ok = dist_credit_points($uid, $points, $remark, $orderno);
    if (!$ok) {
        // 回滚账户
        $dsql->ExecuteNoneQuery("UPDATE sea_dist_account SET available_amount=available_amount+{$m},points_converted=GREATEST(points_converted-{$m},0),version=version+1,update_time={$now} WHERE uid={$uid}");
        return array('ok' => false, 'message' => '积分入账失败，请稍后再试');
    }
    // 同步佣金流水状态：已兑换为积分的部分同样置为已发放
    dist_commission_mark_paid($uid, $cents);
    dist_log($uid, 'commission_adjust', 'user', (string)$uid, array('available' => $m), array('points' => $points), 1, $uid, '佣金转积分');
    return array('ok' => true, 'message' => '已兑换 ' . $points . ' 积分', 'points' => $points);
}

/**
 * 写入积分（rtype=3 分销提成）并同步会员积分
 */
function dist_credit_points($uid, $points, $remark = '', $orderno = '')
{
    global $dsql;
    $uid = intval($uid);
    $points = intval($points);
    if ($uid <= 0 || $points <= 0) {
        return false;
    }
    $member = $dsql->GetOne("SELECT username FROM sea_member WHERE id={$uid} limit 1");
    if (!is_array($member)) {
        return false;
    }
    $uname = addslashes((string)$member['username']);
    if ($orderno === '') {
        $orderno = dist_new_no('FX');
    }
    $ordernoEsc = addslashes($orderno);
    $remarkEsc = addslashes(substr((string)$remark, 0, 255));
    $ip = addslashes(dist_client_ip());
    $ua = addslashes(dist_user_agent());
    $now = time();
    $sql = "INSERT INTO sea_paylog (orderno,uid,uname,rtype,channel,amount,currency,points,ratio,status,trade_no,card_id,card_key,parent_uid,dist_level,dist_rate,remark,ip,user_agent,ext,addtime,paytime)
        VALUES ('{$ordernoEsc}',{$uid},'{$uname}',3,'dist',0,'POINTS',{$points},0,1,'',0,'',0,0,0,'{$remarkEsc}','{$ip}','{$ua}','',{$now},{$now})";
    if (!$dsql->ExecuteNoneQuery($sql)) {
        return false;
    }
    $ok = $dsql->ExecuteNoneQuery("UPDATE sea_member SET points=points+{$points} WHERE id={$uid}");
    if (!$ok) {
        $dsql->ExecuteNoneQuery("DELETE FROM sea_paylog WHERE orderno='{$ordernoEsc}'");
        return false;
    }
    return true;
}
