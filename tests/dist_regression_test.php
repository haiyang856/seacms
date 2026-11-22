<?php
/**
 * 三级分销回归测试（账户补建 + 冻结/清退不被自动开通）
 * 运行：php tests/dist_regression_test.php
 * 退出码：0 全部通过，1 存在失败
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$_SERVER['HTTP_HOST'] = 'localhost';

require_once(dirname(__DIR__) . '/include/common.php');
require_once(dirname(__DIR__) . '/include/dist/bootstrap.php');

global $dsql;

$createdUids = array();
$total = 0;
$fail = 0;

function rt_assert($name, $cond, $detail = '')
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

function rt_make_member()
{
    global $dsql, $createdUids;
    $name = 'fxrt' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10);
    $nameEsc = addslashes($name);
    $pwd = md5('x');
    $email = $nameEsc . '@test.local';
    $old = time() - 60 * 86400;
    $dsql->ExecuteNoneQuery("INSERT INTO sea_member (username,nickname,password,email,regip,regtime,gid,points,state)
        VALUES ('{$nameEsc}','{$nameEsc}','{$pwd}','{$email}','127.0.0.1',{$old},2,0,1)");
    $uid = intval($dsql->GetLastID());
    if ($uid > 0) {
        $createdUids[] = $uid;
    }
    return $uid;
}

register_shutdown_function(function () {
    global $dsql, $createdUids;
    $map = array(
        'sea_dist_account' => 'uid',
        'sea_dist_user' => 'uid',
        'sea_dist_relation' => 'uid',
        'sea_dist_commission' => 'uid',
        'sea_dist_order' => 'buyer_uid',
        'sea_dist_withdraw' => 'uid',
        'sea_dist_log' => 'uid',
        'sea_buy' => 'uid',
    );
    foreach ($createdUids as $uid) {
        $uid = intval($uid);
        if ($uid <= 0) {
            continue;
        }
        foreach ($map as $table => $column) {
            $dsql->ExecuteNoneQuery("DELETE FROM {$table} WHERE {$column}={$uid}");
        }
        $dsql->ExecuteNoneQuery("DELETE FROM sea_member WHERE id={$uid}");
    }
});

/* ============ 一、账户缺失补建 ============ */
echo "== 账户补建 ==\n";

$u1 = rt_make_member();
dist_user_create($u1, 1);
rt_assert('开通分销身份时自动创建账户', !empty(dist_account_get($u1)));

$dsql->ExecuteNoneQuery("DELETE FROM sea_dist_account WHERE uid={$u1}");
$minCents = intval(dist_cfg('min_withdraw', 100)) * 100;
$maxCents = intval(dist_cfg('max_withdraw', 50000)) * 100;
$amount = $minCents > 0 ? $minCents : 100;
if ($maxCents > 0) {
    $amount = min($amount, $maxCents);
}
$res = dist_withdraw_apply($u1, $amount, 'manual', '测试收款账户');
rt_assert('账户缺失时提现不再提示“分销账户不存在”', $res['message'] !== '分销账户不存在', $res['message']);
rt_assert('账户缺失时提现返回准确余额提示', $res['message'] === '可提现余额不足', $res['message']);
rt_assert('提现申请过程中自动补建账户', !empty(dist_account_get($u1)));

$dsql->ExecuteNoneQuery("DELETE FROM sea_dist_account WHERE uid={$u1}");
$res = dist_convert_points($u1, 100);
rt_assert('账户缺失时转积分不再提示“分销账户不存在”', $res['message'] !== '分销账户不存在', $res['message']);
rt_assert('账户缺失时转积分返回余额不足', $res['message'] === '可提现余额不足', $res['message']);
rt_assert('转积分过程中自动补建账户', !empty(dist_account_get($u1)));

/* ============ 二、冻结/清退不被自动开通 ============ */
echo "\n== 状态保护 ==\n";

if (!dist_enabled()) {
    echo "[SKIP] 分销总开关关闭，跳过自动开通相关用例\n";
} else {
    $u2 = rt_make_member();
    dist_user_create($u2, 1);
    $ratio = max(1, intval(dist_cfg('points_per_yuan', 100)));
    $needCents = intval(dist_cfg('consume_threshold', 50)) * 100;
    $needPoints = $needCents > 0 ? (int)ceil($needCents * $ratio / 100) : 0;
    if ($needPoints > 0) {
        $now = time();
        $dsql->ExecuteNoneQuery("INSERT INTO sea_buy (uid,vid,vfrom,vpaypoints,kptime) VALUES ({$u2},0,0,{$needPoints},{$now})");
    }

    $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=2 WHERE uid={$u2}");
    $res = dist_try_activate($u2);
    $row = dist_user_get($u2);
    rt_assert('冻结用户访问不会被自动开通', empty($res['ok']) && intval($row['status']) === 2, $res['message']);
    rt_assert('冻结提示文案正确', strpos((string)$res['message'], '冻结') !== false, $res['message']);

    $res = dist_withdraw_apply($u2, $amount, 'manual', '测试收款账户');
    rt_assert('冻结用户提现被拒绝', empty($res['ok']) && $res['message'] === '分销身份不可用', $res['message']);

    $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=4 WHERE uid={$u2}");
    $res = dist_try_activate($u2);
    $row = dist_user_get($u2);
    rt_assert('清退用户访问不会被自动开通', empty($res['ok']) && intval($row['status']) === 4, $res['message']);
    rt_assert('清退提示文案正确', strpos((string)$res['message'], '清退') !== false, $res['message']);

    $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=5 WHERE uid={$u2}");
    $res = dist_try_activate($u2);
    $row = dist_user_get($u2);
    rt_assert('注销用户访问不会被自动开通', empty($res['ok']) && intval($row['status']) === 5, $res['message']);

    $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=0 WHERE uid={$u2}");
    $res = dist_try_activate($u2);
    $row = dist_user_get($u2);
    rt_assert('待审核用户达标后仍可自动开通', !empty($res['ok']) && intval($row['status']) === 1, $res['message']);

    $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=3 WHERE uid={$u2}");
    $res = dist_try_activate($u2);
    $row = dist_user_get($u2);
    rt_assert('拒绝用户达标后可自动复评开通', !empty($res['ok']) && intval($row['status']) === 1, $res['message']);

    if ($needCents > 0) {
        $u3 = rt_make_member();
        dist_user_create($u3, 1);
        $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=3 WHERE uid={$u3}");
        $res = dist_try_activate($u3);
        $row = dist_user_get($u3);
        rt_assert(
            '拒绝用户条件未达时保持拒绝态',
            empty($res['ok']) && intval($row['status']) === 3 && strpos((string)$res['message'], '未达标') !== false,
            $res['message']
        );
    }

    $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=2 WHERE uid={$u2}");
    $res = dist_try_activate($u2, true);
    $row = dist_user_get($u2);
    rt_assert('后台强制开通可恢复冻结用户', !empty($res['ok']) && intval($row['status']) === 1, $res['message']);
}

/* ============ 三、邀请码生成与插入校验 ============ */
echo "\n== 邀请码 ==\n";

$code = dist_generate_invite_code();
rt_assert('邀请码格式正确', preg_match('/^[A-Z2-9]{8}$/', $code) === 1 || preg_match('/^[0-9A-F]{10}$/', $code) === 1, $code);

$u4 = rt_make_member();
$now = time();
$dsql->ExecuteNoneQuery("INSERT INTO sea_dist_user (uid,invite_code,status,apply_time,create_time,update_time)
    VALUES ({$u4},NULL,0,{$now},{$now},{$now})");
$ok = dist_user_create($u4, 1);
$row = dist_user_get($u4);
rt_assert(
    '激活时补齐缺失的邀请码',
    $ok === true && intval($row['status']) === 1
        && preg_match('/^[A-Z2-9]{8}$/', (string)$row['invite_code']) === 1,
    json_encode($row, JSON_UNESCAPED_UNICODE)
);

$dup = $dsql->GetOne("SELECT COUNT(*) AS c,COUNT(DISTINCT invite_code) AS d FROM sea_dist_user WHERE invite_code IS NOT NULL AND invite_code<>''");
rt_assert('邀请码全局不重复', is_array($dup) && intval($dup['c']) === intval($dup['d']), json_encode($dup));

$again = dist_user_create($u4, 1);
$cnt = $dsql->GetOne("SELECT COUNT(*) AS c FROM sea_dist_user WHERE uid={$u4}");
rt_assert('重复创建不产生重复记录', $again === true && intval($cnt['c']) === 1, json_encode($cnt));

echo "\n合计 {$total} 项，失败 {$fail} 项\n";
exit($fail > 0 ? 1 : 0);
