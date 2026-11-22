<?php
/**
 * 三级分销 - 统一入口
 * 使用：require_once(sea_ROOT . '/include/dist/bootstrap.php');
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/schema.php');
require_once(dirname(__FILE__) . '/log.php');
require_once(dirname(__FILE__) . '/user.php');
require_once(dirname(__FILE__) . '/relation.php');
require_once(dirname(__FILE__) . '/rule.php');
require_once(dirname(__FILE__) . '/account.php');
require_once(dirname(__FILE__) . '/risk.php');
require_once(dirname(__FILE__) . '/commission.php');
require_once(dirname(__FILE__) . '/withdraw.php');
require_once(dirname(__FILE__) . '/stats.php');

dist_ensure_schema();

/**
 * 定时任务：达标自动开通分销身份
 */
function dist_cron_activate($limit = 200)
{
    global $dsql;
    $limit = max(1, min(intval($limit), 1000));
    $days = intval(dist_cfg('register_days', 7));
    $before = time() - $days * 86400;
    $sql = "SELECT m.id FROM sea_member m LEFT JOIN sea_dist_user u ON u.uid=m.id
        WHERE m.state=1 AND m.email<>'' AND m.regtime>0 AND m.regtime<={$before} AND (u.uid IS NULL OR u.status=0 OR u.status=3)
        ORDER BY m.id ASC limit {$limit}";
    $ids = array();
    $dsql->SetQuery($sql);
    $dsql->Execute('dist_cron_act');
    while ($r = $dsql->GetArray('dist_cron_act')) {
        $ids[] = intval($r['id']);
    }
    $ok = 0;
    foreach ($ids as $id) {
        $res = dist_try_activate($id);
        if (!empty($res['ok'])) {
            $ok++;
        }
    }
    return array('scanned' => count($ids), 'activated' => $ok);
}

/**
 * 定时任务：结算到期佣金
 */
function dist_cron_settle($limit = 0)
{
    return dist_settle_due($limit);
}
