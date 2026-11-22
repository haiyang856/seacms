<?php
/**
 * 码支付（易支付协议，MD5 签名）
 * 文档：https://www.mazfu.com/doc/epay_submit
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}
require_once(dirname(__FILE__) . '/config.php');

/**
 * 易支付 MD5 签名：排除 sign、sign_type、空值，ASCII 排序，拼接后追加商户密钥
 */
function netpay_mazfu_sign($params, $key)
{
    unset($params['sign'], $params['sign_type']);
    ksort($params, SORT_STRING);
    $parts = array();
    foreach ($params as $k => $v) {
        if ($v === null || $v === '' || is_array($v) || is_object($v)) {
            continue;
        }
        $parts[] = $k . '=' . $v;
    }
    return md5(implode('&', $parts) . $key);
}

function netpay_mazfu_create($order)
{
    $config = netpay_load_config();
    if ($config['mazfu_enable'] !== 'on') {
        return array('ok' => false, 'error' => '码支付未开启');
    }
    if ($config['mazfu_pid'] === '' || $config['mazfu_key'] === '' || !netpay_url_is_valid($config['mazfu_gateway'])) {
        return array('ok' => false, 'error' => '码支付配置不完整');
    }
    $ext = array();
    if (!empty($order['ext'])) {
        $decoded = json_decode($order['ext'], true);
        if (is_array($decoded)) {
            $ext = $decoded;
        }
    }
    $type = isset($ext['mazfu_type']) ? strtolower($ext['mazfu_type']) : '';
    if (!in_array($type, array('alipay', 'wxpay'), true)) {
        return array('ok' => false, 'error' => '码支付方式无效');
    }
    if ($type === 'alipay' && $config['mazfu_alipay'] !== 'on') {
        return array('ok' => false, 'error' => '码支付支付宝通道未开启');
    }
    if ($type === 'wxpay' && $config['mazfu_wxpay'] !== 'on') {
        return array('ok' => false, 'error' => '码支付微信通道未开启');
    }
    $siteName = isset($GLOBALS['cfg_webname']) ? strip_tags((string)$GLOBALS['cfg_webname']) : '积分充值';
    if ($siteName === '') {
        $siteName = '积分充值';
    }
    $params = array(
        'pid'          => $config['mazfu_pid'],
        'type'         => $type,
        'out_trade_no' => $order['orderno'],
        'notify_url'   => netpay_notify_url('mazfu'),
        'return_url'   => netpay_return_url('mazfu'),
        'name'         => mb_substr($config['pay_subject'], 0, 60, 'UTF-8'),
        'money'        => number_format(floatval($order['amount']), 2, '.', ''),
        'sitename'     => mb_substr($siteName, 0, 60, 'UTF-8'),
        'clientip'     => netpay_client_ip(),
        'device'       => netpay_is_mobile() ? 'mobile' : 'pc',
        'param'        => $order['orderno'],
        'sign_type'    => 'MD5',
    );
    $params['sign'] = netpay_mazfu_sign($params, $config['mazfu_key']);
    return array('ok' => true, 'type' => 'form', 'html' => netpay_auto_form($config['mazfu_gateway'], $params, 'POST'));
}

/**
 * 校验码支付异步通知
 */
function netpay_mazfu_verify_notify($input)
{
    $config = netpay_load_config();
    if (!is_array($input) || empty($input['sign']) || empty($input['out_trade_no'])) {
        return array('ok' => false, 'error' => '通知数据不完整');
    }
    if (empty($config['mazfu_key'])) {
        return array('ok' => false, 'error' => '码支付密钥未配置');
    }
    $sign = strtolower((string)$input['sign']);
    $calc = netpay_mazfu_sign($input, $config['mazfu_key']);
    if (!hash_equals($calc, $sign)) {
        return array('ok' => false, 'error' => '码支付签名验证失败');
    }
    if (isset($input['pid']) && $config['mazfu_pid'] !== '' && !hash_equals($config['mazfu_pid'], (string)$input['pid'])) {
        return array('ok' => false, 'error' => '码支付商户号不匹配');
    }
    $tradeStatus = isset($input['trade_status']) ? (string)$input['trade_status'] : '';
    if ($tradeStatus !== 'TRADE_SUCCESS') {
        return array('ok' => false, 'error' => '交易未成功', 'ignore' => true);
    }
    return array(
        'ok'       => true,
        'orderno'  => (string)$input['out_trade_no'],
        'trade_no' => isset($input['trade_no']) ? (string)$input['trade_no'] : '',
        'amount'   => isset($input['money']) ? floatval($input['money']) : 0,
    );
}
