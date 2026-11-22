<?php
/**
 * 分销与充值模块问题修复验证（对应测试报告问题 2/3/4/5/7）
 * 运行：php tests/fix_verification_test.php
 * 退出码：0 全部通过，1 存在失败
 *
 * 说明：问题 6（无效邀请码注册提示）依赖验证码会话，使用浏览器端到端用例覆盖。
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$_SERVER['HTTP_HOST'] = 'localhost';

require_once(dirname(__DIR__) . '/include/common.php');
require_once(dirname(__DIR__) . '/include/dist/bootstrap.php');
require_once(dirname(__DIR__) . '/include/netpay/order.php');

global $dsql;

$createdUids = array();
$total = 0;
$fail = 0;

function fx_assert($name, $cond, $detail = '')
{
    global $total, $fail;
    $total++;
    if ($cond) {
        echo "[PASS] {$name}\n";
        return;
    }
    $fail++;
    echo "[FAIL] {$name}" . ($detail === '' ? '' : "  => {$detail}") . "\n";
}

function fx_make_member($regtime = null)
{
    global $dsql, $createdUids;
    $name = 'fxfix' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10);
    $nameEsc = addslashes($name);
    $pwd = md5('x');
    $email = $nameEsc . '@test.local';
    $reg = $regtime === null ? time() - 60 * 86400 : intval($regtime);
    $dsql->ExecuteNoneQuery("INSERT INTO sea_member (username,nickname,password,email,regip,regtime,gid,points,state)
        VALUES ('{$nameEsc}','{$nameEsc}','{$pwd}','{$email}','127.0.0.1',{$reg},2,0,1)");
    $uid = intval($dsql->GetLastID());
    if ($uid > 0) {
        $createdUids[] = $uid;
    }
    return $uid;
}

function fx_add_commission($uid, $amountCents, $orderNo, $settleTime)
{
    global $dsql;
    $now = time();
    $amount = dist_money($amountCents);
    $sql = "INSERT INTO sea_dist_commission (order_no,buyer_uid,uid,level,base_amount,rate,amount,status,settle_time,create_time,update_time,remark)
        VALUES ('" . addslashes($orderNo) . "',{$uid},{$uid},1," . dist_money($amountCents * 10) . ",0.10,{$amount},1,{$settleTime},{$now},{$now},'fix-test')";
    $dsql->ExecuteNoneQuery($sql);
    return intval($dsql->GetLastID());
}

function fx_commission_status_count($uid, $status)
{
    global $dsql;
    $row = $dsql->GetOne("SELECT COUNT(*) AS c FROM sea_dist_commission WHERE uid=" . intval($uid) . " AND status=" . intval($status));
    return is_array($row) ? intval($row['c']) : -1;
}

function fx_account($uid)
{
    return dist_account_get($uid);
}

register_shutdown_function(function () {
    global $dsql, $createdUids;
    $map = array(
        array('sea_dist_account', 'uid'),
        array('sea_dist_user', 'uid'),
        array('sea_dist_relation', 'uid'),
        array('sea_dist_relation', 'parent_uid'),
        array('sea_dist_commission', 'uid'),
        array('sea_dist_order', 'buyer_uid'),
        array('sea_dist_withdraw', 'uid'),
        array('sea_dist_log', 'uid'),
        array('sea_buy', 'uid'),
        array('sea_paylog', 'uid'),
    );
    foreach ($createdUids as $uid) {
        $uid = intval($uid);
        if ($uid <= 0) {
            continue;
        }
        foreach ($map as $item) {
            $dsql->ExecuteNoneQuery("DELETE FROM {$item[0]} WHERE {$item[1]}={$uid}");
        }
        $dsql->ExecuteNoneQuery("DELETE FROM sea_member WHERE id={$uid}");
    }
});

$root = dirname(__DIR__);

/* ============ 问题 2：关闭订单状态显示 ============ */
echo "== 问题 2：充值订单关闭状态 ==\n";

fx_assert('状态 2 映射为“已关闭”', netpay_status_name(2) === '已关闭', netpay_status_name(2));
fx_assert('状态 9 仍为“未知”', netpay_status_name(9) === '未知', netpay_status_name(9));
fx_assert('状态 4 仍为“已退款”', netpay_status_name(4) === '已退款', netpay_status_name(4));

$adminPaylog = file_get_contents($root . '/zzz/admin_paylog.php');
fx_assert('后台订单筛选允许 status=2', strpos($adminPaylog, "array('', '0', '1', '2', '4', '9')") !== false);
fx_assert('后台列表状态样式覆盖 status=2', strpos($adminPaylog, "array(0, 1, 2, 4)") !== false);

$paylogTpl = file_get_contents($root . '/zzz/templets/admin_paylog.htm');
fx_assert('后台筛选下拉包含“已关闭”选项', strpos($paylogTpl, 'value="2"') !== false && strpos($paylogTpl, '已关闭') !== false);
fx_assert('后台模板状态样式覆盖 status=2', strpos($paylogTpl, "in_array(\$rowStatus, array(0, 1, 2, 4), true)") !== false);

/* ============ 问题 3：观察期文案与配置口径 ============ */
echo "\n== 问题 3：观察期文案与配置 ==\n";

$observe = intval(dist_cfg('observe_days', 7));
$newbie = intval(dist_cfg('risk_newbie_observe_days', 0));
fx_assert('基础观察期配置为 7 天', $observe === 7, (string)$observe);
fx_assert('新用户风控观察期配置为 30 天', $newbie === 30, (string)$newbie);

$distTpl = file_get_contents($root . '/member/templets/default/distribution.html');
fx_assert('分销首页不再写死 T+7', strpos($distTpl, 'T+7') === false);
fx_assert('分销首页展示动态观察期', strpos($distTpl, '{member:fx_observe_days}') !== false);
fx_assert('分销首页展示新用户风控观察期', strpos($distTpl, '{member:fx_newbie_observe_days}') !== false);

$commTpl = file_get_contents($root . '/member/templets/default/distribution_commission.html');
fx_assert('佣金明细页使用动态观察期', strpos($commTpl, '{member:fx_observe_days}') !== false);

$waitTpl = file_get_contents($root . '/member/templets/default/distribution_wait.html');
fx_assert('未开通页补绑期限使用动态注册天数', strpos($waitTpl, '{member:fx_register_days}') !== false);

$distModule = file_get_contents($root . '/member/modules/distribution.php');
fx_assert('模块计算新用户风控观察期', strpos($distModule, '$fxNewbieObserveDays') !== false);
fx_assert('模块下发观察期占位符', strpos($distModule, "'fx_observe_days' =>") !== false && strpos($distModule, "'fx_newbie_observe_days' =>") !== false);

/* ============ 问题 5：绑定入口可见性 ============ */
echo "\n== 问题 5：绑定邀请人入口可见性 ==\n";

fx_assert('分销首页绑定表单改用 fx_can_bind 控制', strpos($distTpl, '{if:{member:fx_can_bind}==1}') !== false);
fx_assert('分销首页不再用 fx_has_parent 控制绑定表单', strpos($distTpl, '{if:{member:fx_has_parent}==0}') === false);
fx_assert('未开通页绑定表单改用 fx_can_bind 控制', strpos($waitTpl, '{if:{member:fx_can_bind}==1}') !== false);
fx_assert('未开通页不再用 fx_has_parent 控制绑定表单', strpos($waitTpl, '{if:{member:fx_has_parent}==0}') === false);
fx_assert('模块按“无上级/无计佣订单/无下级/期限内”计算可绑定', strpos($distModule, '$fxHasPaid') !== false && strpos($distModule, '$fxHasChild') !== false && strpos($distModule, '$fxWithinReg') !== false);

/* ============ 问题 7：充值金额小数位 ============ */
echo "\n== 问题 7：充值金额小数位限制 ==\n";

$u7 = fx_make_member();
$uname7 = 'fxfix' . $u7;
$before = $dsql->GetOne("SELECT COUNT(*) AS c FROM sea_paylog WHERE uid={$u7} AND rtype=2");

$res = netpay_create_order($u7, $uname7, '1.234', 'CNY', 'alipay');
fx_assert('三位小数下单被拒绝', empty($res['ok']) && $res['error'] === '充值金额最多保留两位小数', json_encode($res, JSON_UNESCAPED_UNICODE));
$mid = $dsql->GetOne("SELECT COUNT(*) AS c FROM sea_paylog WHERE uid={$u7} AND rtype=2");
fx_assert('三位小数不产生订单记录', is_array($before) && is_array($mid) && intval($before['c']) === intval($mid['c']), json_encode($mid));

$res = netpay_create_order($u7, $uname7, '1.23', 'CNY', 'alipay');
fx_assert('两位小数下单成功', !empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));
if (!empty($res['ok'])) {
    $orderNo = (string)$res['order']['orderno'];
    $row = $dsql->GetOne("SELECT amount FROM sea_paylog WHERE orderno='" . addslashes($orderNo) . "' limit 1");
    fx_assert('订单金额精确到两位小数', is_array($row) && abs(floatval($row['amount']) - 1.23) < 0.0001, json_encode($row));
}

$res = netpay_create_order($u7, $uname7, '10.5', 'CNY', 'alipay');
fx_assert('一位小数下单成功', !empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));

$netpayTpl = file_get_contents($root . '/member/templets/default/netpay.html');
fx_assert('充值页前端拦截三位小数', strpos($netpayTpl, '/\\.\\d{3,}$/') !== false && strpos($netpayTpl, '充值金额最多保留两位小数') !== false);

/* ============ 问题 4：提现后佣金状态同步 ============ */
echo "\n== 问题 4：提现成功同步佣金为已发放 ==\n";

$u4 = fx_make_member();
dist_user_create($u4, 1);
fx_assert('测试会员分销身份已开通', intval(dist_user_get($u4)['status'] ?? 0) === 1);

$now4 = time();
fx_add_commission($u4, 6000, 'FIXPAY4A' . $u4, $now4 - 10);
fx_add_commission($u4, 4000, 'FIXPAY4B' . $u4, $now4 - 5);
$dsql->ExecuteNoneQuery("UPDATE sea_dist_account SET total_amount=100.00,pending_amount=0,available_amount=100.00,frozen_amount=0,withdrawn_amount=0,version=version+1 WHERE uid={$u4}");
fx_assert('初始可提现佣金 2 笔', fx_commission_status_count($u4, 1) === 2, (string)fx_commission_status_count($u4, 1));

$apply = dist_withdraw_apply($u4, 10000, 'manual', '测试收款账户');
fx_assert('提现申请成功', !empty($apply['ok']), json_encode($apply, JSON_UNESCAPED_UNICODE));

$wd = $dsql->GetOne("SELECT id,status FROM sea_dist_withdraw WHERE withdraw_no='" . addslashes($apply['withdraw_no'] ?? '') . "' limit 1");
fx_assert('提现单状态为待审核', is_array($wd) && intval($wd['status']) === 0, json_encode($wd));

$audit = dist_withdraw_audit(intval($wd['id']), true, 'fix_tester', '验证用例');
fx_assert('提现审核通过', !empty($audit['ok']), json_encode($audit, JSON_UNESCAPED_UNICODE));

$pay = dist_withdraw_pay(intval($wd['id']), 'fix_tester', 'FIXTRADE' . $u4);
fx_assert('提现确认打款成功', !empty($pay['ok']), json_encode($pay, JSON_UNESCAPED_UNICODE));

fx_assert('打款后佣金全部转为已发放(3)', fx_commission_status_count($u4, 3) === 2, (string)fx_commission_status_count($u4, 3));
fx_assert('打款后无“可提现”佣金', fx_commission_status_count($u4, 1) === 0, (string)fx_commission_status_count($u4, 1));

$acct4 = fx_account($u4);
fx_assert('账户可提现余额归零', is_array($acct4) && abs(floatval($acct4['available_amount'])) < 0.0001, json_encode($acct4));
fx_assert('账户已提现金额为 100', is_array($acct4) && abs(floatval($acct4['withdrawn_amount']) - 100) < 0.0001, json_encode($acct4));
fx_assert('账户冻结金额归零', is_array($acct4) && abs(floatval($acct4['frozen_amount'])) < 0.0001, json_encode($acct4));

/* ============ 问题 4 补充：FIFO 标记与幂等 ============ */
echo "\n== 问题 4：佣金 FIFO 标记规则 ==\n";

$u5 = fx_make_member();
dist_user_create($u5, 1);
fx_add_commission($u5, 3000, 'FIXFIFOA' . $u5, $now4 - 30);
fx_add_commission($u5, 3000, 'FIXFIFOB' . $u5, $now4 - 20);
fx_add_commission($u5, 3000, 'FIXFIFOC' . $u5, $now4 - 10);

$mark = dist_commission_mark_paid($u5, 4500);
fx_assert('FIFO 标记达到目标金额即停止', !empty($mark['ok']) && intval($mark['paid']) === 6000 && intval($mark['rows']) === 2, json_encode($mark));
fx_assert('FIFO 后剩余 1 笔可提现', fx_commission_status_count($u5, 1) === 1, (string)fx_commission_status_count($u5, 1));
fx_assert('FIFO 后 2 笔已发放', fx_commission_status_count($u5, 3) === 2, (string)fx_commission_status_count($u5, 3));

$mark2 = dist_commission_mark_paid($u5, 0);
fx_assert('金额为 0 时不误标记', empty($mark2['ok']) && fx_commission_status_count($u5, 1) === 1, json_encode($mark2));

$mark3 = dist_commission_mark_paid($u5, 3000);
fx_assert('剩余佣金可继续标记', !empty($mark3['ok']) && fx_commission_status_count($u5, 1) === 0 && fx_commission_status_count($u5, 3) === 3, json_encode($mark3));

/* ============ 已打款提现的后续操作 ============ */
echo "\n== 已打款提现：打款失败/撤销/重新审核 ==\n";

function fx_setup_paid($tag)
{
    global $dsql;
    $uid = fx_make_member();
    dist_user_create($uid, 1);
    $now = time();
    fx_add_commission($uid, 6000, 'WDOP' . $tag . 'A' . $uid, $now - 10);
    fx_add_commission($uid, 4000, 'WDOP' . $tag . 'B' . $uid, $now - 5);
    $dsql->ExecuteNoneQuery("UPDATE sea_dist_account SET total_amount=100.00,pending_amount=0,available_amount=100.00,frozen_amount=0,withdrawn_amount=0,version=version+1 WHERE uid={$uid}");
    $apply = dist_withdraw_apply($uid, 10000, 'manual', '测试账户');
    $wd = $dsql->GetOne("SELECT id FROM sea_dist_withdraw WHERE withdraw_no='" . addslashes($apply['withdraw_no'] ?? '') . "' limit 1");
    $id = is_array($wd) ? intval($wd['id']) : 0;
    dist_withdraw_audit($id, true, 'fix_tester', '审核');
    dist_withdraw_pay($id, 'fix_tester', 'TRADE' . $uid);
    return array($uid, $id);
}

function fx_assert_paid_refunded($label, $uid, $withdrawId, $expectStatus)
{
    global $dsql;
    $wd = $dsql->GetOne("SELECT status FROM sea_dist_withdraw WHERE id=" . intval($withdrawId));
    fx_assert($label . '：提现单状态为' . $expectStatus, is_array($wd) && intval($wd['status']) === $expectStatus, json_encode($wd));
    $acct = dist_account_get($uid);
    fx_assert($label . '：可提现余额恢复 100.00', is_array($acct) && abs(floatval($acct['available_amount']) - 100) < 0.0001, json_encode($acct));
    fx_assert($label . '：已提现金额归零', is_array($acct) && abs(floatval($acct['withdrawn_amount'])) < 0.0001, json_encode($acct));
    fx_assert($label . '：佣金恢复为可提现 2 笔', fx_commission_status_count($uid, 1) === 2 && fx_commission_status_count($uid, 3) === 0, fx_commission_status_count($uid, 1) . '/' . fx_commission_status_count($uid, 3));
}

list($uF, $idF) = fx_setup_paid('F');
$res = dist_withdraw_fail($idF, 'fix_tester', '银行退回');
fx_assert('已打款单可标记打款失败', !empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));
fx_assert_paid_refunded('打款失败', $uF, $idF, 5);

list($uC, $idC) = fx_setup_paid('C');
$res = dist_withdraw_cancel($idC, 0, 'fix_tester');
fx_assert('已打款单可由管理员撤销', !empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));
fx_assert_paid_refunded('撤销提现', $uC, $idC, 6);

list($uR, $idR) = fx_setup_paid('R');
$res = dist_withdraw_audit($idR, false, 'fix_tester', '复核驳回');
fx_assert('已打款单可重新审核驳回', !empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));
fx_assert_paid_refunded('重新审核驳回', $uR, $idR, 4);

list($uA, $idA) = fx_setup_paid('A');
$res = dist_withdraw_audit($idA, true, 'fix_tester', '复核通过');
fx_assert('已打款单可复核通过', !empty($res['ok']) && $res['message'] === '已复核确认', json_encode($res, JSON_UNESCAPED_UNICODE));
$wdA = $dsql->GetOne("SELECT status FROM sea_dist_withdraw WHERE id={$idA}");
fx_assert('复核通过后仍为已打款', is_array($wdA) && intval($wdA['status']) === 3, json_encode($wdA));
$acctA = dist_account_get($uA);
fx_assert('复核通过不改动资金', is_array($acctA) && abs(floatval($acctA['available_amount'])) < 0.0001 && abs(floatval($acctA['withdrawn_amount']) - 100) < 0.0001, json_encode($acctA));
fx_assert('复核通过佣金保持已发放', fx_commission_status_count($uA, 3) === 2, (string)fx_commission_status_count($uA, 3));

/* ============ 全状态可操作 ============ */
echo "\n== 全状态可操作：驳回/失败/撤销/待审核单 ==\n";

// 已驳回 -> 重新审核通过
list($uR1, $idR1) = fx_setup_paid('X');
dist_withdraw_audit($idR1, false, 'fix_tester', '驳回');
$res = dist_withdraw_audit($idR1, true, 'fix_tester', '重新通过');
fx_assert('已驳回单可重新审核通过', !empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));
$wdR1 = $dsql->GetOne("SELECT status FROM sea_dist_withdraw WHERE id={$idR1}");
$acctR1 = dist_account_get($uR1);
fx_assert('重新通过后状态为审核通过', is_array($wdR1) && intval($wdR1['status']) === 1, json_encode($wdR1));
fx_assert('重新通过后金额重新冻结', is_array($acctR1) && abs(floatval($acctR1['frozen_amount']) - 100) < 0.0001 && abs(floatval($acctR1['available_amount'])) < 0.0001, json_encode($acctR1));
fx_assert('重新通过后佣金仍为可提现', fx_commission_status_count($uR1, 1) === 2, (string)fx_commission_status_count($uR1, 1));

// 已驳回 -> 直接确认打款
list($uR2, $idR2) = fx_setup_paid('Y');
dist_withdraw_audit($idR2, false, 'fix_tester', '驳回');
$res = dist_withdraw_pay($idR2, 'fix_tester', 'REPAY' . $uR2);
fx_assert('已驳回单可重新确认打款', !empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));
$wdR2 = $dsql->GetOne("SELECT status FROM sea_dist_withdraw WHERE id={$idR2}");
$acctR2 = dist_account_get($uR2);
fx_assert('重新打款后状态为已打款', is_array($wdR2) && intval($wdR2['status']) === 3, json_encode($wdR2));
fx_assert('重新打款后金额进入已提现', is_array($acctR2) && abs(floatval($acctR2['withdrawn_amount']) - 100) < 0.0001 && abs(floatval($acctR2['available_amount'])) < 0.0001, json_encode($acctR2));
fx_assert('重新打款后佣金恢复已发放', fx_commission_status_count($uR2, 3) === 2, (string)fx_commission_status_count($uR2, 3));

// 打款失败 -> 重新审核通过
list($uR3, $idR3) = fx_setup_paid('Z');
dist_withdraw_fail($idR3, 'fix_tester', '银行退回');
$res = dist_withdraw_audit($idR3, true, 'fix_tester', '重新通过');
fx_assert('打款失败单可重新审核通过', !empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));
$wdR3 = $dsql->GetOne("SELECT status FROM sea_dist_withdraw WHERE id={$idR3}");
$acctR3 = dist_account_get($uR3);
fx_assert('打款失败后重新通过状态为审核通过', is_array($wdR3) && intval($wdR3['status']) === 1, json_encode($wdR3));
fx_assert('打款失败后重新通过金额重新冻结', is_array($acctR3) && abs(floatval($acctR3['frozen_amount']) - 100) < 0.0001, json_encode($acctR3));

// 已撤销 -> 重新审核通过
list($uR4, $idR4) = fx_setup_paid('W');
dist_withdraw_cancel($idR4, 0, 'fix_tester');
$res = dist_withdraw_audit($idR4, true, 'fix_tester', '重新通过');
fx_assert('已撤销单可重新审核通过', !empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));
$wdR4 = $dsql->GetOne("SELECT status FROM sea_dist_withdraw WHERE id={$idR4}");
$acctR4 = dist_account_get($uR4);
fx_assert('已撤销后重新通过状态为审核通过', is_array($wdR4) && intval($wdR4['status']) === 1, json_encode($wdR4));
fx_assert('已撤销后重新通过金额重新冻结', is_array($acctR4) && abs(floatval($acctR4['frozen_amount']) - 100) < 0.0001, json_encode($acctR4));

// 待审核 -> 直接确认打款
$uD = fx_make_member();
dist_user_create($uD, 1);
$nowD = time();
fx_add_commission($uD, 6000, 'DIRECTPAYA' . $uD, $nowD - 10);
fx_add_commission($uD, 4000, 'DIRECTPAYB' . $uD, $nowD - 5);
$dsql->ExecuteNoneQuery("UPDATE sea_dist_account SET total_amount=100.00,pending_amount=0,available_amount=100.00,frozen_amount=0,withdrawn_amount=0,version=version+1 WHERE uid={$uD}");
$applyD = dist_withdraw_apply($uD, 10000, 'manual', '测试账户');
$wdD = $dsql->GetOne("SELECT id FROM sea_dist_withdraw WHERE withdraw_no='" . addslashes($applyD['withdraw_no'] ?? '') . "' limit 1");
$idD = is_array($wdD) ? intval($wdD['id']) : 0;
$res = dist_withdraw_pay($idD, 'fix_tester', 'DIRECTPAY' . $uD);
fx_assert('待审核单可直接确认打款', !empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));
$wdD2 = $dsql->GetOne("SELECT status FROM sea_dist_withdraw WHERE id={$idD}");
$acctD = dist_account_get($uD);
fx_assert('直接打款后状态为已打款', is_array($wdD2) && intval($wdD2['status']) === 3, json_encode($wdD2));
fx_assert('直接打款后佣金恢复已发放', fx_commission_status_count($uD, 3) === 2, (string)fx_commission_status_count($uD, 3));

// 幂等：重复打款失败不重复退款
list($uI, $idI) = fx_setup_paid('I');
dist_withdraw_fail($idI, 'fix_tester', '第一次');
$res = dist_withdraw_fail($idI, 'fix_tester', '第二次');
$acctI = dist_account_get($uI);
fx_assert('重复打款失败仍返回成功', !empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));
fx_assert('重复打款失败不重复退款', is_array($acctI) && abs(floatval($acctI['available_amount']) - 100) < 0.0001, json_encode($acctI));

// 余额不足：驳回后余额被使用，重新通过应失败且状态不变
list($uN, $idN) = fx_setup_paid('N');
dist_withdraw_audit($idN, false, 'fix_tester', '驳回');
$dsql->ExecuteNoneQuery("UPDATE sea_dist_account SET available_amount=0 WHERE uid={$uN}");
$res = dist_withdraw_audit($idN, true, 'fix_tester', '重新通过');
$wdN = $dsql->GetOne("SELECT status FROM sea_dist_withdraw WHERE id={$idN}");
fx_assert('余额不足时重新通过被拒绝', empty($res['ok']), json_encode($res, JSON_UNESCAPED_UNICODE));
fx_assert('余额不足时状态保持已驳回', is_array($wdN) && intval($wdN['status']) === 4, json_encode($wdN));

/* ============ 回归：冻结用户不可提现 ============ */
echo "\n== 回归：提现前置校验 ==\n";

$u6 = fx_make_member();
dist_user_create($u6, 1);
$dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=2 WHERE uid={$u6}");
$res = dist_withdraw_apply($u6, 10000, 'manual', '测试收款账户');
fx_assert('冻结用户提现仍被拒绝', empty($res['ok']) && $res['message'] === '分销身份不可用', json_encode($res, JSON_UNESCAPED_UNICODE));

echo "\n合计 {$total} 项，失败 {$fail} 项\n";
exit($fail > 0 ? 1 : 0);
