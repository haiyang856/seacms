<?php
/**
 * USDT 收款（BEpusdt）
 * 文档：https://github.com/v03413/BEpusdt
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}
require_once(dirname(__FILE__) . '/config.php');

function netpay_usdt_sign_value($value)
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_float($value)) {
        return netpay_amount_plain($value);
    }
    if (is_int($value)) {
        return (string)$value;
    }
    return (string)$value;
}

/**
 * BEpusdt 签名：排除 signature 与空值，ASCII 排序，拼接后直接追加通信密钥
 */
function netpay_usdt_sign($params, $token)
{
    unset($params['signature']);
    ksort($params, SORT_STRING);
    $parts = array();
    foreach ($params as $k => $v) {
        if ($v === null || $v === '' || is_array($v) || is_object($v)) {
            continue;
        }
        $parts[] = $k . '=' . netpay_usdt_sign_value($v);
    }
    return md5(implode('&', $parts) . $token);
}

function netpay_usdt_json($payload)
{
    $parts = array();
    foreach ($payload as $k => $v) {
        if ($k === 'amount' || $k === 'timeout') {
            $parts[] = json_encode((string)$k) . ':' . $v;
        } else {
            $parts[] = json_encode((string)$k) . ':' . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    return '{' . implode(',', $parts) . '}';
}

function netpay_usdt_create($order)
{
    $config = netpay_load_config();
    if ($config['usdt_enable'] !== 'on') {
        return array('ok' => false, 'error' => 'USDT 收款未开启');
    }
    if (!netpay_url_is_valid($config['usdt_gateway']) || $config['usdt_token'] === '') {
        return array('ok' => false, 'error' => 'USDT 收款配置不完整');
    }
    $tradeType = $config['usdt_trade_type'] !== '' ? $config['usdt_trade_type'] : 'usdt.trc20';
    $amountPlain = netpay_amount_plain($order['amount']);
    $expire = max(60, intval($config['pay_order_expire']));
    // fiat=USD 且 rate=1，确保实际支付 USDT 数量与订单填写数量一致
    $payload = array(
        'order_id'     => $order['orderno'],
        'amount'       => $amountPlain,
        'fiat'         => 'USD',
        'rate'         => '1',
        'trade_type'   => $tradeType,
        'name'         => mb_substr($config['pay_subject'], 0, 60, 'UTF-8'),
        'notify_url'   => netpay_notify_url('usdt'),
        'redirect_url' => netpay_return_url('usdt'),
        'timeout'      => $expire,
    );
    $payload['signature'] = netpay_usdt_sign($payload, $config['usdt_token']);
    $url = rtrim($config['usdt_gateway'], '/') . '/api/v1/order/create-transaction';
    $resp = netpay_http_request(
        $url,
        netpay_usdt_json($payload),
        array('Content-Type: application/json; charset=utf-8', 'Accept: application/json'),
        20
    );
    if (!$resp['ok']) {
        netpay_log('USDT 下单请求失败', array('orderno' => $order['orderno'], 'error' => $resp['error']));
        return array('ok' => false, 'error' => 'USDT 下单请求失败：' . $resp['error']);
    }
    $data = json_decode($resp['body'], true);
    if (!is_array($data)) {
        netpay_log('USDT 下单返回解析失败', array('orderno' => $order['orderno']));
        return array('ok' => false, 'error' => 'USDT 返回数据异常');
    }
    $statusCode = isset($data['status_code']) ? intval($data['status_code']) : (isset($data['code']) ? intval($data['code']) : 0);
    if ($statusCode !== 200 && $statusCode !== 0) {
        $msg = isset($data['message']) ? $data['message'] : '未知错误';
        return array('ok' => false, 'error' => 'USDT 下单失败：' . $msg);
    }
    $paymentUrl = '';
    if (isset($data['data']['payment_url'])) {
        $paymentUrl = (string)$data['data']['payment_url'];
    } elseif (isset($data['payment_url'])) {
        $paymentUrl = (string)$data['payment_url'];
    }
    if (!netpay_url_is_valid($paymentUrl)) {
        netpay_log('USDT 未返回有效收银台地址', array('orderno' => $order['orderno']));
        return array('ok' => false, 'error' => 'USDT 未返回有效收银台地址');
    }
    return array('ok' => true, 'type' => 'redirect', 'url' => $paymentUrl);
}

/**
 * 校验 BEpusdt 异步通知
 */
function netpay_usdt_verify_notify($data)
{
    $config = netpay_load_config();
    if (!is_array($data) || empty($data['order_id']) || empty($data['signature'])) {
        return array('ok' => false, 'error' => '通知数据不完整');
    }
    if (empty($config['usdt_token'])) {
        return array('ok' => false, 'error' => 'USDT 通信密钥未配置');
    }
    $calc = netpay_usdt_sign($data, $config['usdt_token']);
    if (!hash_equals($calc, strtolower((string)$data['signature']))) {
        return array('ok' => false, 'error' => 'USDT 签名验证失败');
    }
    $status = isset($data['status']) ? intval($data['status']) : 0;
    if ($status === 1) {
        return array('ok' => false, 'error' => '等待支付', 'ignore' => true, 'waiting' => true);
    }
    if ($status !== 2) {
        return array('ok' => false, 'error' => '交易未成功', 'ignore' => true);
    }
    $amount = isset($data['amount']) ? floatval($data['amount']) : 0;
    return array(
        'ok'       => true,
        'orderno'  => (string)$data['order_id'],
        'trade_no' => isset($data['block_transaction_id']) ? (string)$data['block_transaction_id'] : '',
        'amount'   => $amount,
    );
}
