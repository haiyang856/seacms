<?php
/**
 * 三级分销 - 风控基础校验
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

/**
 * 订单级风险评估
 * @return array ok/observe_days/flags
 */
function dist_risk_assess($buyerUid, $baseCents, $tradeType = 5)
{
    global $dsql;
    $buyerUid = intval($buyerUid);
    $baseCents = intval($baseCents);
    $tradeType = intval($tradeType);
    $flags = array();
    if ($baseCents <= 0) {
        return array('ok' => false, 'observe_days' => 0, 'flags' => array('zero_amount'));
    }
    if (in_array($tradeType, array(3, 4), true) && intval(dist_cfg('recharge_commission', 0)) !== 1) {
        return array('ok' => false, 'observe_days' => 0, 'flags' => array('recharge_disabled'));
    }
    $days = intval(dist_cfg('observe_days', 7));
    $member = $dsql->GetOne("SELECT regtime FROM sea_member WHERE id={$buyerUid} limit 1");
    if (is_array($member)) {
        $newbie = intval(dist_cfg('risk_newbie_minutes', 10)) * 60;
        if ($newbie > 0 && intval($member['regtime']) > 0 && intval($member['regtime']) > time() - $newbie) {
            $days = max($days, intval(dist_cfg('risk_newbie_observe_days', 30)));
            $flags[] = 'newbie_fast_consume';
        }
    }
    return array('ok' => true, 'observe_days' => $days, 'flags' => $flags);
}

/**
 * 收益人佣金上限检查
 * @return array allow/remark/settle_days
 */
function dist_risk_beneficiary_cap($uid, $cents, $settleTime)
{
    global $dsql;
    $uid = intval($uid);
    $cents = intval($cents);
    if ($uid <= 0 || $cents <= 0) {
        return array('allow' => false, 'remark' => '', 'settle_time' => $settleTime);
    }
    $dayStart = strtotime(date('Y-m-d') . ' 00:00:00');
    $monthStart = strtotime(date('Y-m-01') . ' 00:00:00');
    $row = $dsql->GetOne("SELECT SUM(amount) AS s FROM sea_dist_commission WHERE uid={$uid} AND status IN (0,1,2,3) AND create_time>={$dayStart}");
    $today = is_array($row) ? intval(round(floatval($row['s']) * 100)) : 0;
    $row = $dsql->GetOne("SELECT SUM(amount) AS s FROM sea_dist_commission WHERE uid={$uid} AND status IN (0,1,2,3) AND create_time>={$monthStart}");
    $month = is_array($row) ? intval(round(floatval($row['s']) * 100)) : 0;
    $dailyCap = intval(dist_cfg('daily_commission_cap', 20000)) * 100;
    $monthCap = intval(dist_cfg('monthly_commission_cap', 100000)) * 100;
    if ($dailyCap > 0 && $today + $cents > $dailyCap) {
        return array('allow' => true, 'remark' => '触发日佣金上限，待人工复核', 'settle_time' => time() + 7 * 86400);
    }
    if ($monthCap > 0 && $month + $cents > $monthCap) {
        return array('allow' => true, 'remark' => '触发月佣金上限，待人工复核', 'settle_time' => time() + 7 * 86400);
    }
    return array('allow' => true, 'remark' => '', 'settle_time' => $settleTime);
}
