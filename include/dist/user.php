<?php
/**
 * 三级分销 - 分销员身份与准入
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

function dist_user_get($uid)
{
    global $dsql;
    $uid = intval($uid);
    if ($uid <= 0) {
        return array();
    }
    $row = $dsql->GetOne("SELECT * FROM sea_dist_user WHERE uid={$uid} limit 1");
    return is_array($row) ? $row : array();
}

/**
 * 检查邀请码是否已被占用
 */
function dist_invite_code_taken($code)
{
    global $dsql;
    $row = $dsql->GetOne("SELECT uid FROM sea_dist_user WHERE invite_code='" . addslashes($code) . "' limit 1");
    return is_array($row);
}

/**
 * 生成全局唯一邀请码
 */
function dist_generate_invite_code()
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $len = strlen($chars);
    for ($i = 0; $i < 12; $i++) {
        $code = '';
        for ($j = 0; $j < 8; $j++) {
            $code .= $chars[random_int(0, $len - 1)];
        }
        if (!dist_invite_code_taken($code)) {
            return $code;
        }
    }
    // 兜底：10 位十六进制串同样查重，全部冲突则返回空串由调用方判定失败
    for ($i = 0; $i < 12; $i++) {
        $code = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 10));
        if (!dist_invite_code_taken($code)) {
            return $code;
        }
    }
    return '';
}

/**
 * 确保分销员记录存在
 */
function dist_user_create($uid, $status = 1)
{
    global $dsql;
    $uid = intval($uid);
    $status = intval($status);
    if ($uid <= 0) {
        return false;
    }
    $now = time();
    $row = dist_user_get($uid);
    if (!empty($row)) {
        if ($status === 1 && intval($row['status']) !== 1) {
            $code = $row['invite_code'] !== null && $row['invite_code'] !== '' ? $row['invite_code'] : dist_generate_invite_code();
            if ($code === '') {
                return false;
            }
            $codeEsc = addslashes($code);
            $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=1,invite_code='{$codeEsc}',update_time={$now} WHERE uid={$uid}");
            $fresh = dist_user_get($uid);
            if (empty($fresh) || intval($fresh['status']) !== 1) {
                return false;
            }
        }
        if ($status === 1) {
            dist_account_ensure($uid);
        }
        return true;
    }
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $code = $status === 1 ? dist_generate_invite_code() : null;
        if ($status === 1 && $code === '') {
            return false;
        }
        $codeSql = $code === null ? 'NULL' : "'" . addslashes($code) . "'";
        $sql = "INSERT IGNORE INTO sea_dist_user (uid,invite_code,status,grade,apply_time,create_time,update_time)
            VALUES ({$uid},{$codeSql},{$status},1,{$now},{$now},{$now})";
        $affected = intval($dsql->ExecuteNoneQuery2($sql));
        if ($affected > 0) {
            dist_log($uid, 'audit', 'user', (string)$uid, null, array('status' => $status, 'invite_code' => $code), 3, 0, '自动开通分销身份');
            if ($status === 1) {
                dist_account_ensure($uid);
            }
            return true;
        }
        $fresh = dist_user_get($uid);
        if (!empty($fresh)) {
            // 并发请求已创建记录：状态一致才视为成功
            if ($status === 1 && intval($fresh['status']) === 1) {
                dist_account_ensure($uid);
                return true;
            }
            return intval($fresh['status']) === $status;
        }
        // 记录仍不存在，说明邀请码冲突被忽略，换新码重试
    }
    return false;
}

/**
 * 用户累计有效消费（元，分）
 */
function dist_user_consume_cents($uid)
{
    global $dsql;
    $uid = intval($uid);
    if ($uid <= 0) {
        return 0;
    }
    $points = 0;
    $row = $dsql->GetOne("SELECT SUM(vpaypoints) AS s FROM sea_buy WHERE uid={$uid}");
    if (is_array($row)) {
        $points += intval($row['s']);
    }
    $member = $dsql->GetOne("SELECT username FROM sea_member WHERE id={$uid}");
    if (is_array($member) && isset($member['username'])) {
        $uname = addslashes($member['username']);
        $row2 = $dsql->GetOne("SELECT SUM(paypoints) AS s FROM sea_hyzbuy WHERE uname='{$uname}'");
        if (is_array($row2)) {
            $points += intval($row2['s']);
        }
    }
    return dist_points_to_cents($points);
}

/**
 * 达标自动开通（失败返回原因）
 * @return array ok/message/status
 */
function dist_try_activate($uid, $force = false)
{
    global $dsql;
    $uid = intval($uid);
    if ($uid <= 0) {
        return array('ok' => false, 'message' => '用户不存在', 'status' => 0);
    }
    if (!dist_enabled()) {
        return array('ok' => false, 'message' => '分销功能未开启', 'status' => 0);
    }
    $row = dist_user_get($uid);
    if (!empty($row) && intval($row['status']) === 1) {
        $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET last_active_time=" . time() . " WHERE uid={$uid}");
        return array('ok' => true, 'message' => '已是分销员', 'status' => 1);
    }
    // 冻结/清退/注销属于人工处置结果，不得被自动开通逻辑覆盖；拒绝状态允许按条件自动复评
    if (!empty($row) && !$force) {
        $curStatus = intval($row['status']);
        if ($curStatus === 2 || $curStatus === 4 || $curStatus === 5) {
            $blocked = array(
                2 => '分销身份已冻结，请联系客服',
                4 => '分销身份已被清退',
                5 => '分销身份已注销'
            );
            return array(
                'ok' => false,
                'message' => isset($blocked[$curStatus]) ? $blocked[$curStatus] : '分销身份不可用',
                'status' => $curStatus
            );
        }
    }
    if (!$force && intval(dist_cfg('auto_activate', 1)) !== 1) {
        if (empty($row)) {
            dist_user_create($uid, 0);
        }
        return array('ok' => false, 'message' => '需提交申请并等待审核', 'status' => 0);
    }
    $member = $dsql->GetOne("SELECT id,username,email,state,regtime FROM sea_member WHERE id={$uid} limit 1");
    if (!is_array($member)) {
        return array('ok' => false, 'message' => '会员不存在', 'status' => 0);
    }
    if (intval($member['state']) !== 1) {
        return array('ok' => false, 'message' => '账号状态异常', 'status' => 0);
    }
    if (trim((string)$member['email']) === '') {
        return array('ok' => false, 'message' => '请先完成邮箱验证', 'status' => 0);
    }
    $days = intval(dist_cfg('register_days', 7));
    $regtime = intval($member['regtime']);
    if ($regtime > 0 && $days > 0 && $regtime > time() - $days * 86400) {
        return array('ok' => false, 'message' => '注册未满 ' . $days . ' 天', 'status' => 0);
    }
    $threshold = intval(dist_cfg('consume_threshold', 50)) * 100;
    $consume = dist_user_consume_cents($uid);
    if ($consume < $threshold) {
        return array('ok' => false, 'message' => '累计有效消费未达标', 'status' => 0, 'consume' => $consume, 'threshold' => $threshold);
    }
    dist_user_create($uid, 1);
    $row = dist_user_get($uid);
    if (!empty($row) && intval($row['status']) === 1) {
        // 首次开通后尝试绑定注册时记录的推荐关系
        return array('ok' => true, 'message' => '分销身份已开通', 'status' => 1);
    }
    return array('ok' => false, 'message' => '开通失败，请稍后再试', 'status' => 0);
}

/**
 * 分销员基础统计
 */
function dist_user_summary($uid)
{
    global $dsql;
    $uid = intval($uid);
    $team = array(1 => 0, 2 => 0, 3 => 0);
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_relation WHERE parent_uid={$uid} AND status=1");
    $team[1] = is_array($row) ? intval($row['dd']) : 0;
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_relation WHERE p2_uid={$uid} AND status=1");
    $team[2] = is_array($row) ? intval($row['dd']) : 0;
    $row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_relation WHERE p3_uid={$uid} AND status=1");
    $team[3] = is_array($row) ? intval($row['dd']) : 0;
    return $team;
}
