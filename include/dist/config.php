<?php
/**
 * 三级分销 - 配置与通用工具
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

$dist_cfg = array(
    // 总开关与层级开关
    'enabled' => 1,
    'level1' => 1,
    'level2' => 1,
    'level3' => 1,
    // 基础费率
    'rate1' => 0.10,
    'rate2' => 0.05,
    'rate3' => 0.03,
    // 单级与总额上限
    'max_rate1' => 0.15,
    'max_rate2' => 0.10,
    'max_rate3' => 0.08,
    'max_total' => 0.20,
    // 等级系数
    'grade_factor_1' => 1.00,
    'grade_factor_2' => 1.05,
    'grade_factor_3' => 1.10,
    'grade_factor_4' => 1.15,
    // 计佣口径
    'points_per_yuan' => 100,
    'exchange_rate' => 100,
    'recharge_commission' => 0,
    // 结算
    'observe_days' => 7,
    'settle_batch' => 200,
    // 提现
    'min_withdraw' => 100,
    'max_withdraw' => 50000,
    'withdraw_fee' => 0,
    'withdraw_daily_limit' => 3,
    // 准入
    'auto_activate' => 1,
    'consume_threshold' => 50,
    'register_days' => 7,
    'max_direct' => 5000,
    'max_team' => 50000,
    // 风控
    'max_commission_order' => 5000,
    'daily_commission_cap' => 20000,
    'monthly_commission_cap' => 100000,
    'risk_newbie_minutes' => 10,
    'risk_newbie_observe_days' => 30,
);

$distCfgFile = sea_ROOT . '/data/admin/dist.php';
if (is_file($distCfgFile)) {
    $dist_cfg_saved = array();
    include $distCfgFile;
    if (is_array($dist_cfg_saved)) {
        foreach ($dist_cfg_saved as $distK => $distV) {
            if (array_key_exists($distK, $dist_cfg)) {
                $dist_cfg[$distK] = $distV;
            }
        }
    }
}
// 允许在函数作用域内 include 本文件：统一同步到全局，供 dist_cfg() 读取
$GLOBALS['dist_cfg'] = $dist_cfg;

/**
 * 读取配置
 */
function dist_cfg($key, $default = null)
{
    global $dist_cfg;
    return array_key_exists($key, $dist_cfg) ? $dist_cfg[$key] : $default;
}

function dist_enabled()
{
    return intval(dist_cfg('enabled', 0)) === 1;
}

/**
 * 金额（分）转数据库字符串
 */
function dist_money($cents)
{
    $cents = intval($cents);
    $sign = $cents < 0 ? '-' : '';
    $cents = abs($cents);
    return $sign . intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
}

/**
 * 元（字符串/数值）转分，保留两位，非法返回 false
 */
function dist_parse_amount($value)
{
    $value = trim((string)$value);
    if ($value === '' || !preg_match('/^\d{1,9}(\.\d{1,2})?$/', $value)) {
        return false;
    }
    $parts = explode('.', $value);
    $yuan = intval($parts[0]);
    $cent = isset($parts[1]) ? intval(str_pad($parts[1], 2, '0')) : 0;
    return $yuan * 100 + $cent;
}

/**
 * 积分转计佣基数（分）
 */
function dist_points_to_cents($points)
{
    $points = intval($points);
    $ratio = intval(dist_cfg('points_per_yuan', 100));
    if ($points <= 0 || $ratio <= 0) {
        return 0;
    }
    return intdiv($points * 100, $ratio);
}

/**
 * 金额（分）转积分
 */
function dist_cents_to_points($cents)
{
    $rate = intval(dist_cfg('exchange_rate', 100));
    if ($cents <= 0 || $rate <= 0) {
        return 0;
    }
    return intdiv(intval($cents) * $rate, 100);
}

/**
 * 费率转基点（万分之一）
 */
function dist_bp($rate)
{
    return intval(round(floatval($rate) * 10000));
}

function dist_rate_text($bp)
{
    return number_format(intval($bp) / 10000, 4, '.', '');
}

function dist_client_ip()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = (string)$_SERVER['REMOTE_ADDR'];
    } else {
        $ip = '';
    }
    return substr(preg_replace('/[^0-9a-fA-F\.:]/', '', $ip), 0, 45);
}

function dist_user_agent()
{
    return isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
}

function dist_request_id()
{
    static $rid = '';
    if ($rid === '') {
        $rid = date('YmdHis') . bin2hex(random_bytes(4));
    }
    return $rid;
}

/**
 * 生成订单/提现单号
 */
function dist_new_no($prefix)
{
    return $prefix . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/**
 * 用户名脱敏
 */
function dist_mask_name($name)
{
    $name = (string)$name;
    $len = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($len <= 2) {
        return $len === 0 ? '匿名' : mb_substr($name, 0, 1, 'UTF-8') . '**';
    }
    $head = mb_substr($name, 0, 2, 'UTF-8');
    $tail = $len > 4 ? mb_substr($name, -2, 2, 'UTF-8') : '';
    return $head . '***' . $tail;
}

function dist_trade_type_name($type)
{
    $map = array(1 => '视频购买', 2 => '会员开通', 3 => '在线充值', 4 => '充值卡', 5 => '其他');
    return isset($map[intval($type)]) ? $map[intval($type)] : '其他';
}

function dist_order_status_name($status)
{
    $map = array(0 => '待结算', 1 => '已结算', 2 => '部分退款', 3 => '全额退款', 4 => '作废');
    return isset($map[intval($status)]) ? $map[intval($status)] : '未知';
}

function dist_commission_status_name($status)
{
    $map = array(0 => '待结算', 1 => '可提现', 2 => '提现冻结', 3 => '已发放', 4 => '已取消', 5 => '已冲正');
    return isset($map[intval($status)]) ? $map[intval($status)] : '未知';
}

function dist_withdraw_status_name($status)
{
    $map = array(0 => '待审核', 1 => '审核通过', 2 => '打款中', 3 => '已打款', 4 => '已驳回', 5 => '打款失败', 6 => '已撤销');
    return isset($map[intval($status)]) ? $map[intval($status)] : '未知';
}

function dist_user_status_name($status)
{
    $map = array(0 => '待审核', 1 => '正常', 2 => '冻结', 3 => '已拒绝', 4 => '清退', 5 => '注销');
    return isset($map[intval($status)]) ? $map[intval($status)] : '未知';
}

function dist_grade_name($grade)
{
    $map = array(1 => '普通', 2 => '铜牌', 3 => '银牌', 4 => '金牌');
    return isset($map[intval($grade)]) ? $map[intval($grade)] : '普通';
}
