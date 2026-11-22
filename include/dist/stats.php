<?php
/**
 * 三级分销 - 查询与统计
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

/**
 * 会员分销概览
 */
function dist_member_overview($uid)
{
    global $dsql;
    $uid = intval($uid);
    $user = dist_user_get($uid);
    $acct = dist_account_get($uid);
    if (empty($acct)) {
        $acct = array(
            'total_amount' => '0.00', 'pending_amount' => '0.00', 'available_amount' => '0.00',
            'frozen_amount' => '0.00', 'withdrawn_amount' => '0.00', 'negative_amount' => '0.00',
            'points_converted' => '0.00'
        );
    }
    $team = dist_user_summary($uid);
    $consume = dist_user_consume_cents($uid);
    $threshold = intval(dist_cfg('consume_threshold', 50)) * 100;
    $progress = $threshold > 0 ? min(100, intval($consume * 100 / $threshold)) : 100;
    return array(
        'user' => $user,
        'account' => $acct,
        'team' => $team,
        'consume' => dist_money($consume),
        'threshold' => dist_money($threshold),
        'progress' => $progress
    );
}

/**
 * 团队列表
 */
function dist_team_list($uid, $level = 1, $offset = 0, $limit = 20)
{
    global $dsql;
    $uid = intval($uid);
    $level = intval($level);
    $offset = max(0, intval($offset));
    $limit = max(1, min(intval($limit), 50));
    $fieldMap = array(1 => 'r.parent_uid', 2 => 'r.p2_uid', 3 => 'r.p3_uid');
    if (!isset($fieldMap[$level])) {
        return array('rows' => array(), 'total' => 0);
    }
    $field = $fieldMap[$level];
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_relation r WHERE {$field}={$uid} AND r.status=1");
    $total = is_array($row) ? intval($row['dd']) : 0;
    $rows = array();
    if ($total <= 0) {
        return array('rows' => array(), 'total' => 0);
    }
    $dsql->SetQuery("SELECT r.uid,r.bind_time,r.bind_source,m.username,m.regtime FROM sea_dist_relation r
        LEFT JOIN sea_member m ON m.id=r.uid
        WHERE {$field}={$uid} AND r.status=1 ORDER BY r.bind_time DESC limit {$offset},{$limit}");
    $dsql->Execute('dist_team');
    while ($item = $dsql->GetArray('dist_team')) {
        $child = intval($item['uid']);
        $order = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_order WHERE buyer_uid={$child}");
        $contribute = $dsql->GetOne("SELECT SUM(amount) AS s FROM sea_dist_commission WHERE uid={$uid} AND buyer_uid={$child} AND status IN (0,1,2,3)");
        $rows[] = array(
            'uid' => $child,
            'name' => dist_mask_name($item['username']),
            'level' => $level,
            'regtime' => $item['regtime'] > 0 ? date('Y-m-d', intval($item['regtime'])) : '-',
            'bindtime' => $item['bind_time'] > 0 ? date('Y-m-d', intval($item['bind_time'])) : '-',
            'source' => member_e((string)$item['bind_source']),
            'active' => is_array($order) && intval($order['dd']) > 0 ? '有效消费' : '未消费',
            'activeclass' => is_array($order) && intval($order['dd']) > 0 ? 'is-active' : 'is-idle',
            'contribute' => is_array($contribute) ? number_format(floatval($contribute['s']), 2, '.', '') : '0.00'
        );
    }
    return array('rows' => $rows, 'total' => $total);
}

/**
 * 佣金明细
 */
function dist_commission_list($uid, $status = -1, $offset = 0, $limit = 20)
{
    global $dsql;
    $uid = intval($uid);
    $offset = max(0, intval($offset));
    $limit = max(1, min(intval($limit), 50));
    $where = "uid={$uid}";
    if (intval($status) >= 0) {
        $where .= " AND status=" . intval($status);
    }
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_commission WHERE {$where}");
    $total = is_array($row) ? intval($row['dd']) : 0;
    $rows = array();
    if ($total > 0) {
        $dsql->SetQuery("SELECT * FROM sea_dist_commission WHERE {$where} ORDER BY id DESC limit {$offset},{$limit}");
        $dsql->Execute('dist_comm');
        while ($item = $dsql->GetArray('dist_comm')) {
            $buyer = $dsql->GetOne("SELECT username FROM sea_member WHERE id=" . intval($item['buyer_uid']));
            $rows[] = array(
                'time' => date('Y-m-d H:i', intval($item['create_time'])),
                'order' => member_e($item['order_no']),
                'buyer' => is_array($buyer) ? dist_mask_name($buyer['username']) : '匿名',
                'level' => 'L' . intval($item['level']),
                'base' => number_format(floatval($item['base_amount']), 2, '.', ''),
                'rate' => number_format(floatval($item['rate']) * 100, 2, '.', '') . '%',
                'amount' => number_format(floatval($item['amount']), 2, '.', ''),
                'status' => dist_commission_status_name($item['status']),
                'statusclass' => 'cs' . intval($item['status']),
                'settle' => intval($item['settle_time']) > 0 ? date('Y-m-d', intval($item['settle_time'])) : '-',
                'remark' => member_e((string)$item['remark'])
            );
        }
    }
    return array('rows' => $rows, 'total' => $total);
}

/**
 * 提现记录
 */
function dist_withdraw_list($uid, $offset = 0, $limit = 20)
{
    global $dsql;
    $uid = intval($uid);
    $offset = max(0, intval($offset));
    $limit = max(1, min(intval($limit), 50));
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_withdraw WHERE uid={$uid}");
    $total = is_array($row) ? intval($row['dd']) : 0;
    $rows = array();
    if ($total > 0) {
        $dsql->SetQuery("SELECT * FROM sea_dist_withdraw WHERE uid={$uid} ORDER BY id DESC limit {$offset},{$limit}");
        $dsql->Execute('dist_wd');
        while ($item = $dsql->GetArray('dist_wd')) {
            $rows[] = array(
                'id' => intval($item['id']),
                'no' => member_e($item['withdraw_no']),
                'time' => date('Y-m-d H:i', intval($item['create_time'])),
                'amount' => number_format(floatval($item['amount']), 2, '.', ''),
                'fee' => number_format(floatval($item['fee']), 2, '.', ''),
                'real' => number_format(floatval($item['real_amount']), 2, '.', ''),
                'channel' => member_e($item['channel']),
                'status' => dist_withdraw_status_name($item['status']),
                'statusclass' => 'ws' . intval($item['status']),
                'can_cancel' => intval($item['status']) === 0 ? 1 : 0
            );
        }
    }
    return array('rows' => $rows, 'total' => $total);
}

/**
 * 后台总览
 */
function dist_admin_summary()
{
    global $dsql;
    $out = array(
        'users' => 0, 'active_users' => 0, 'frozen_users' => 0,
        'pending' => 0, 'available' => 0, 'frozen' => 0, 'withdrawn' => 0, 'negative' => 0,
        'orders' => 0, 'gmv' => 0, 'commission' => 0, 'refund' => 0,
        'wait_withdraw' => 0, 'wait_commission' => 0, 'risk_commission' => 0
    );
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd,SUM(status=1) AS ok,SUM(status=2) AS frozen FROM sea_dist_user");
    if (is_array($row)) {
        $out['users'] = intval($row['dd']);
        $out['active_users'] = intval($row['ok']);
        $out['frozen_users'] = intval($row['frozen']);
    }
    $row = $dsql->GetOne("SELECT SUM(pending_amount) AS p,SUM(available_amount) AS a,SUM(frozen_amount) AS f,SUM(withdrawn_amount) AS w,SUM(negative_amount) AS n FROM sea_dist_account");
    if (is_array($row)) {
        $out['pending'] = floatval($row['p']);
        $out['available'] = floatval($row['a']);
        $out['frozen'] = floatval($row['f']);
        $out['withdrawn'] = floatval($row['w']);
        $out['negative'] = floatval($row['n']);
    }
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd,SUM(base_amount) AS g,SUM(refund_amount) AS r FROM sea_dist_order");
    if (is_array($row)) {
        $out['orders'] = intval($row['dd']);
        $out['gmv'] = floatval($row['g']);
        $out['refund'] = floatval($row['r']);
    }
    $row = $dsql->GetOne("SELECT SUM(amount) AS s FROM sea_dist_commission WHERE status IN (0,1,2,3)");
    if (is_array($row)) {
        $out['commission'] = floatval($row['s']);
    }
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_withdraw WHERE status IN (0,1,2)");
    $out['wait_withdraw'] = is_array($row) ? intval($row['dd']) : 0;
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_commission WHERE status=0");
    $out['wait_commission'] = is_array($row) ? intval($row['dd']) : 0;
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_commission WHERE status=0 AND remark<>''");
    $out['risk_commission'] = is_array($row) ? intval($row['dd']) : 0;
    return $out;
}

/**
 * 近 N 日佣金趋势
 */
function dist_daily_series($days = 14)
{
    global $dsql;
    $days = max(1, min(intval($days), 60));
    $start = strtotime(date('Y-m-d', strtotime('-' . ($days - 1) . ' days')) . ' 00:00:00');
    $rows = array();
    $dsql->SetQuery("SELECT FROM_UNIXTIME(create_time,'%Y-%m-%d') AS d,SUM(amount) AS s,COUNT(*) AS c FROM sea_dist_commission WHERE create_time>={$start} AND status IN (0,1,2,3) GROUP BY d ORDER BY d ASC");
    $dsql->Execute('dist_daily');
    while ($r = $dsql->GetArray('dist_daily')) {
        $rows[$r['d']] = array('amount' => floatval($r['s']), 'count' => intval($r['c']));
    }
    $out = array();
    for ($i = 0; $i < $days; $i++) {
        $d = date('Y-m-d', $start + $i * 86400);
        $out[] = array(
            'date' => $d,
            'amount' => isset($rows[$d]) ? $rows[$d]['amount'] : 0,
            'count' => isset($rows[$d]) ? $rows[$d]['count'] : 0
        );
    }
    return $out;
}
