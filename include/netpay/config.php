<?php
/**
 * 在线充值核心配置与公共函数
 * 所有在线充值相关代码统一存放于 include/netpay/
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

/**
 * 默认配置
 */
function netpay_default_config()
{
    return array(
        // 支付宝（手机网站支付 H5）
        'alipay_enable'       => 'off',
        'alipay_appid'        => '',
        'alipay_private_key'  => '',
        'alipay_public_key'   => '',
        'alipay_gateway'      => 'https://openapi.alipay.com/gateway.do',
        // 微信支付（微信内 JSAPI，移动端 H5，桌面端 Native 扫码，APIv2）
        'wechat_enable'       => 'off',
        'wechat_appid'        => '',
        'wechat_secret'       => '',
        'wechat_mchid'        => '',
        'wechat_key'          => '',
        'wechat_gateway'      => 'https://api.mch.weixin.qq.com/pay/unifiedorder',
        // 码支付（易支付协议）
        'mazfu_enable'        => 'off',
        'mazfu_pid'           => '',
        'mazfu_key'           => '',
        'mazfu_gateway'       => 'https://www.mazfu.com/xpay/epay/submit.php',
        'mazfu_alipay'        => 'on',
        'mazfu_wxpay'         => 'on',
        'mazfu_qqpay'         => 'off',
        'mazfu_bank'          => 'off',
        'mazfu_jdpay'         => 'off',
        'mazfu_paypal'        => 'off',
        // USDT 收款（BEpusdt）
        'usdt_enable'         => 'off',
        'usdt_gateway'        => '',
        'usdt_token'          => '',
        'usdt_trade_type'     => 'usdt.trc20',
        // 站内积分定价
        'pay_cny_points'      => '100',
        'pay_usdt_points'     => '700',
        // 通用
        'pay_min_money'       => '1',
        'pay_max_money'       => '5000',
        'pay_order_expire'    => '900',
        'pay_notify_base'     => '',
        'pay_subject'         => '站内积分充值',
    );
}

function netpay_config_file()
{
    return sea_DATA . '/admin/pay.php';
}

/**
 * 读取配置（文件缺失时使用默认值）
 */
function netpay_load_config($reload = false)
{
    static $config = null;
    if ($config !== null && !$reload) {
        return $config;
    }
    $config = netpay_default_config();
    $file = netpay_config_file();
    if (is_file($file)) {
        $pay_config = null;
        $ret = @include $file;
        if (is_array($ret)) {
            $config = array_merge($config, $ret);
        } elseif (is_array($pay_config)) {
            $config = array_merge($config, $pay_config);
        }
    }
    return $config;
}

function netpay_cfg($key, $default = '')
{
    $config = netpay_load_config();
    return isset($config[$key]) ? $config[$key] : $default;
}

function netpay_url_is_valid($url)
{
    if (!is_string($url) || $url === '') {
        return false;
    }
    $parts = @parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }
    if (!in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
        return false;
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }
    return true;
}

function netpay_onoff($value)
{
    return $value === 'on' ? 'on' : 'off';
}

/**
 * 校验并规范化配置
 * @return array array('ok'=>bool,'config'=>array,'errors'=>array)
 */
function netpay_validate_config($input)
{
    $config = netpay_default_config();
    $errors = array();
    if (!is_array($input)) {
        return array('ok' => false, 'config' => $config, 'errors' => array('无效的配置数据'));
    }
    foreach ($config as $key => $default) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $value = $input[$key];
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $value = trim((string)$value);
        // 过滤控制字符，保留换行（密钥可能为多行）
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        $config[$key] = $value;
    }

    $onoffKeys = array('alipay_enable', 'wechat_enable', 'mazfu_enable', 'usdt_enable');
    foreach (array_keys(netpay_mazfu_type_map()) as $type) {
        $onoffKeys[] = 'mazfu_' . $type;
    }
    foreach ($onoffKeys as $k) {
        $config[$k] = netpay_onoff($config[$k]);
    }

    // 网关地址校验
    foreach (array('alipay_gateway', 'wechat_gateway', 'mazfu_gateway', 'usdt_gateway') as $k) {
        if ($config[$k] !== '' && !netpay_url_is_valid($config[$k])) {
            $errors[] = '网关地址格式不正确：' . $k;
        }
    }
    if ($config['pay_notify_base'] !== '' && !netpay_url_is_valid($config['pay_notify_base'])) {
        $errors[] = '回调基础地址必须是完整的 http(s) 地址';
    }

    // 商户标识白名单
    foreach (array('alipay_appid', 'wechat_appid', 'wechat_mchid', 'mazfu_pid') as $k) {
        if ($config[$k] !== '' && !preg_match('/^[0-9a-zA-Z_\-]{1,64}$/', $config[$k])) {
            $errors[] = '商户标识格式不正确：' . $k;
        }
    }
    if ($config['usdt_trade_type'] !== '' && !preg_match('/^[0-9a-zA-Z_.\-]{1,32}$/', $config['usdt_trade_type'])) {
        $errors[] = 'USDT 交易类型格式不正确';
    }
    if (strlen($config['alipay_private_key']) > 8192 || strlen($config['alipay_public_key']) > 8192) {
        $errors[] = '支付宝密钥长度超出限制';
    }
    if (strlen($config['wechat_key']) > 128 || strlen($config['wechat_secret']) > 128 || strlen($config['mazfu_key']) > 128 || strlen($config['usdt_token']) > 256) {
        $errors[] = '支付密钥长度超出限制';
    }

    // 积分比例
    foreach (array('pay_cny_points', 'pay_usdt_points') as $k) {
        if (!is_numeric($config[$k]) || floatval($config[$k]) <= 0 || floatval($config[$k]) > 1000000) {
            $errors[] = '积分兑换比例必须为大于 0 的数字';
        }
    }
    // 金额范围
    if (!is_numeric($config['pay_min_money']) || floatval($config['pay_min_money']) <= 0) {
        $errors[] = '单笔最低金额必须大于 0';
    }
    if (!is_numeric($config['pay_max_money']) || floatval($config['pay_max_money']) <= 0) {
        $errors[] = '单笔最高金额必须大于 0';
    }
    if (is_numeric($config['pay_min_money']) && is_numeric($config['pay_max_money'])
        && floatval($config['pay_min_money']) > floatval($config['pay_max_money'])) {
        $errors[] = '单笔最低金额不能大于最高金额';
    }
    if (!is_numeric($config['pay_order_expire']) || intval($config['pay_order_expire']) < 60 || intval($config['pay_order_expire']) > 86400) {
        $errors[] = '订单有效期需在 60 - 86400 秒之间';
    }
    if ($config['pay_subject'] === '' || mb_strlen($config['pay_subject'], 'UTF-8') > 60) {
        $errors[] = '商品名称不能为空且不能超过 60 个字符';
    }

    // 启用渠道的必填项校验
    if ($config['alipay_enable'] === 'on') {
        if ($config['alipay_appid'] === '' || $config['alipay_private_key'] === '' || $config['alipay_public_key'] === '') {
            $errors[] = '启用支付宝时必须填写 APPID、应用私钥和支付宝公钥';
        }
    }
    if ($config['wechat_enable'] === 'on') {
        if ($config['wechat_appid'] === '' || $config['wechat_mchid'] === '' || $config['wechat_key'] === '') {
            $errors[] = '启用微信支付时必须填写 APPID、商户号和 API 密钥';
        }
    }
    if ($config['mazfu_enable'] === 'on') {
        if ($config['mazfu_pid'] === '' || $config['mazfu_key'] === '') {
            $errors[] = '启用码支付时必须填写商户 ID 和商户密钥';
        }
        $mazfuTypeOn = false;
        foreach (array_keys(netpay_mazfu_type_map()) as $type) {
            if ($config['mazfu_' . $type] === 'on') {
                $mazfuTypeOn = true;
                break;
            }
        }
        if (!$mazfuTypeOn) {
            $errors[] = '码支付至少需要开启一种支付方式';
        }
    }
    if ($config['usdt_enable'] === 'on') {
        if (!netpay_url_is_valid($config['usdt_gateway']) || $config['usdt_token'] === '') {
            $errors[] = '启用 USDT 收款时必须填写 BEpusdt 地址和通信密钥';
        }
    }

    return array('ok' => empty($errors), 'config' => $config, 'errors' => $errors);
}

/**
 * 原子写入配置文件
 */
function netpay_save_config($config)
{
    $file = netpay_config_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        return array(false, '配置目录不存在');
    }
    if (is_file($file) && !is_writable($file)) {
        return array(false, '配置文件不可写：data/admin/pay.php');
    }
    if (!is_file($file) && !is_writable($dir)) {
        return array(false, '配置目录不可写：data/admin');
    }
    $content = "<?php\n"
        . "// 在线充值配置（由后台自动生成，请勿手工修改为可执行代码）\n"
        . "if (!defined('sea_INC')) { exit('Request Error!'); }\n"
        . "return " . var_export($config, true) . ";\n";
    $tmp = $file . '.tmp' . getmypid();
    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        return array(false, '写入临时配置文件失败');
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return array(false, '替换配置文件失败，请检查目录权限');
    }
    @chmod($file, 0640);
    return array(true, '');
}

/**
 * 站点基础地址（用于回调/跳转）
 */
function netpay_base_url()
{
    $custom = trim((string)netpay_cfg('pay_notify_base'));
    if ($custom !== '' && netpay_url_is_valid($custom)) {
        return rtrim($custom, '/');
    }
    $https = false;
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        $https = true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        $https = true;
    }
    if (!empty($_SERVER['SERVER_PORT']) && intval($_SERVER['SERVER_PORT']) === 443) {
        $https = true;
    }
    $host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : '';
    if (!preg_match('/^[0-9a-zA-Z\.\-]+(:\d{1,5})?$/', $host)) {
        $fallback = isset($GLOBALS['cfg_basehost']) ? (string)$GLOBALS['cfg_basehost'] : '';
        if (preg_match('#^https?://#i', $fallback)) {
            return rtrim($fallback, '/');
        }
        $host = isset($_SERVER['SERVER_NAME']) ? preg_replace('/[^0-9a-zA-Z\.\-]/', '', $_SERVER['SERVER_NAME']) : 'localhost';
    }
    $cmspath = isset($GLOBALS['cfg_cmspath']) ? trim((string)$GLOBALS['cfg_cmspath'], '/') : '';
    return ($https ? 'https' : 'http') . '://' . $host . ($cmspath !== '' ? '/' . $cmspath : '');
}

function netpay_notify_url($channel = '')
{
    $url = netpay_base_url() . '/include/netpay/notify.php';
    return $channel !== '' ? $url . '?channel=' . urlencode($channel) : $url;
}

function netpay_return_url($channel = '')
{
    $url = netpay_base_url() . '/include/netpay/return.php';
    return $channel !== '' ? $url . '?channel=' . urlencode($channel) : $url;
}

/**
 * 金额格式化：保留两位小数，去除多余的 0
 */
function netpay_amount_plain($amount)
{
    $amount = number_format((float)$amount, 2, '.', '');
    if (strpos($amount, '.') !== false) {
        $amount = rtrim(rtrim($amount, '0'), '.');
    }
    return $amount === '' ? '0' : $amount;
}

function netpay_amount_cent($amount)
{
    return intval(round(floatval($amount) * 100));
}

/**
 * 校验充值金额，返回规范化金额或 false
 */
function netpay_normalize_amount($amount)
{
    if (!is_numeric($amount)) {
        return false;
    }
    $amount = round(floatval($amount), 2);
    $min = floatval(netpay_cfg('pay_min_money', 1));
    $max = floatval(netpay_cfg('pay_max_money', 5000));
    if ($amount < $min || $amount > $max || $amount <= 0) {
        return false;
    }
    return $amount;
}

/**
 * 按币种计算积分
 */
function netpay_points_for($amount, $currency)
{
    $currency = strtoupper($currency);
    $ratio = $currency === 'USDT' ? floatval(netpay_cfg('pay_usdt_points', 0)) : floatval(netpay_cfg('pay_cny_points', 0));
    if ($ratio <= 0) {
        return 0;
    }
    return intval(floor(floatval($amount) * $ratio));
}

function netpay_ratio_for($currency)
{
    return strtoupper($currency) === 'USDT' ? floatval(netpay_cfg('pay_usdt_points', 0)) : floatval(netpay_cfg('pay_cny_points', 0));
}

/**
 * 生成 10 位数字订单号（时分秒 6 位 + 4 位随机数，自动查重）
 */
function netpay_new_order_no()
{
    global $dsql;
    for ($i = 0; $i < 10; $i++) {
        $no = date('His') . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        if (!is_object($dsql)) {
            return $no;
        }
        $exists = $dsql->GetOne("SELECT id FROM sea_paylog WHERE orderno='{$no}' limit 1");
        if (!is_array($exists)) {
            return $no;
        }
    }
    return date('His') . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

function netpay_client_ip()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }
    return substr($ip, 0, 45);
}

function netpay_html($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * HTTP 请求
 * @return array ok/body/code/error
 */
function netpay_http_request($url, $body = null, $headers = array(), $timeout = 15)
{
    if (!netpay_url_is_valid($url)) {
        return array('ok' => false, 'body' => '', 'code' => 0, 'error' => '无效的请求地址');
    }
    $timeout = max(3, min(60, intval($timeout)));
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno ? curl_error($ch) : '';
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        if ($errno) {
            return array('ok' => false, 'body' => '', 'code' => $code, 'error' => $error);
        }
        return array('ok' => true, 'body' => (string)$result, 'code' => $code, 'error' => '');
    }
    $opts = array('http' => array('timeout' => $timeout, 'ignore_errors' => true, 'header' => implode("\r\n", $headers)));
    if ($body !== null) {
        $opts['http']['method'] = 'POST';
        $opts['http']['content'] = $body;
    }
    $ctx = stream_context_create($opts);
    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) {
        return array('ok' => false, 'body' => '', 'code' => 0, 'error' => 'HTTP 请求失败');
    }
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = intval($m[1]);
    }
    return array('ok' => true, 'body' => (string)$result, 'code' => $code, 'error' => '');
}

/**
 * 生成自动提交表单
 */
function netpay_auto_form($action, $params, $method = 'POST')
{
    $method = strtoupper($method) === 'GET' ? 'GET' : 'POST';
    $html = '<form id="netpaySubmit" action="' . netpay_html($action) . '" method="' . $method . '">';
    foreach ($params as $k => $v) {
        $html .= '<input type="hidden" name="' . netpay_html($k) . '" value="' . netpay_html($v) . '" />';
    }
    $html .= '<noscript><button type="submit">继续支付</button></noscript></form>';
    $html .= '<script>document.getElementById("netpaySubmit").submit();</script>';
    return $html;
}

function netpay_rtype_name($type)
{
    $map = array(
        1 => '充值卡',
        2 => '在线支付',
        3 => '分销',
        4 => '签到奖励',
        5 => '推广链接',
        6 => '注册赠送',
    );
    $type = intval($type);
    return isset($map[$type]) ? $map[$type] : '其他';
}

/**
 * 是否为移动端访问（用于优先选择 H5 支付）
 */
function netpay_is_mobile()
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower((string)$_SERVER['HTTP_USER_AGENT']) : '';
    if ($ua === '') {
        return false;
    }
    if (preg_match('/ipad|tablet|playbook|silk/', $ua)) {
        return true;
    }
    return (bool)preg_match('/mobile|android|iphone|ipod|windows phone|micromessenger|alipayclient/', $ua);
}

function netpay_channel_name($channel)
{
    $map = array(
        'card' => '充值卡',
        'alipay' => '支付宝',
        'wechat' => '微信支付',
        'mazfu' => '码支付',
        'usdt' => 'USDT',
        'sign' => '签到',
        'promo' => '推广链接',
        'reg' => '注册赠送',
    );
    return isset($map[$channel]) ? $map[$channel] : ($channel === '' ? '-' : $channel);
}

function netpay_status_name($status)
{
    $status = intval($status);
    if ($status === 0) {
        return '待支付';
    }
    if ($status === 1) {
        return '已完成';
    }
    if ($status === 4) {
        return '已退款';
    }
    return '未知';
}

function netpay_currency_name($currency)
{
    $map = array('CNY' => '人民币', 'USDT' => 'USDT', 'POINTS' => '积分');
    $currency = strtoupper($currency);
    return isset($map[$currency]) ? $map[$currency] : $currency;
}

/**
 * 码支付（易支付协议）支持的支付方式
 * key 同时作为配置项后缀（mazfu_xxx）与提交给平台的 type 值
 * @return array
 */
function netpay_mazfu_type_map()
{
    return array(
        'alipay' => array('name' => '支付宝', 'desc' => '码支付-支付宝'),
        'wxpay'  => array('name' => '微信支付', 'desc' => '码支付-微信支付'),
        'qqpay'  => array('name' => 'QQ钱包', 'desc' => '码支付-QQ钱包'),
        'bank'   => array('name' => '网银支付', 'desc' => '码支付-网银支付'),
        'jdpay'  => array('name' => '京东支付', 'desc' => '码支付-京东支付'),
        'paypal' => array('name' => 'PayPal', 'desc' => '码支付-PayPal'),
    );
}

/**
 * 当前可用支付方式
 * @return array
 */
function netpay_available_channels()
{
    $list = array();
    $mobile = netpay_is_mobile();
    if (netpay_cfg('alipay_enable') === 'on') {
        $list[] = array('channel' => 'alipay', 'name' => '支付宝', 'currency' => 'CNY', 'desc' => '手机网站支付 H5');
    }
    if (netpay_cfg('wechat_enable') === 'on') {
        $list[] = array('channel' => 'wechat', 'name' => '微信支付', 'currency' => 'CNY', 'desc' => $mobile ? 'H5 支付' : '扫码支付');
    }
    if (netpay_cfg('mazfu_enable') === 'on') {
        foreach (netpay_mazfu_type_map() as $type => $meta) {
            if (netpay_cfg('mazfu_' . $type) === 'on') {
                $list[] = array(
                    'channel'  => 'mazfu',
                    'sub'      => $type,
                    'name'     => $meta['desc'],
                    'currency' => 'CNY',
                    'desc'     => $mobile ? 'H5 聚合支付' : '第三方聚合支付',
                );
            }
        }
    }
    if (netpay_cfg('usdt_enable') === 'on') {
        $list[] = array('channel' => 'usdt', 'name' => 'USDT', 'currency' => 'USDT', 'desc' => 'BEpusdt 链上收款');
    }
    return $list;
}

/**
 * XML 转义
 */
function netpay_xml_cdata($str)
{
    return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string)$str) . ']]>';
}
