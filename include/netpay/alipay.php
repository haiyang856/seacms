<?php
/**
 * 标准支付宝手机网站支付（alipay.trade.wap.pay，RSA2）
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}
require_once(dirname(__FILE__) . '/config.php');

function netpay_alipay_private_pem($key)
{
    $key = trim((string)$key);
    if ($key === '') {
        return '';
    }
    if (strpos($key, '-----BEGIN') !== false) {
        return str_replace("\r", '', $key);
    }
    $body = preg_replace('/\s+/', '', $key);
    return "-----BEGIN PRIVATE KEY-----\n" . chunk_split($body, 64, "\n") . "-----END PRIVATE KEY-----\n";
}

function netpay_alipay_public_pem($key)
{
    $key = trim((string)$key);
    if ($key === '') {
        return '';
    }
    if (strpos($key, '-----BEGIN CERTIFICATE-----') !== false) {
        return str_replace("\r", '', $key);
    }
    if (strpos($key, '-----BEGIN') !== false) {
        return str_replace("\r", '', $key);
    }
    $body = preg_replace('/\s+/', '', $key);
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($body, 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * 待签名字符串：除 sign 外全部参与，按 ASCII 排序
 */
function netpay_alipay_sign_content($params, $exclude = array('sign'))
{
    ksort($params, SORT_STRING);
    $parts = array();
    foreach ($params as $k => $v) {
        if (in_array($k, $exclude, true)) {
            continue;
        }
        if ($v === null || $v === '') {
            continue;
        }
        if (is_array($v) || is_object($v)) {
            continue;
        }
        $parts[] = $k . '=' . $v;
    }
    return implode('&', $parts);
}

function netpay_alipay_sign($content, $privateKey)
{
    $pem = netpay_alipay_private_pem($privateKey);
    $res = $pem === '' ? false : @openssl_pkey_get_private($pem);
    if ($res === false) {
        return false;
    }
    $signature = '';
    $ok = @openssl_sign($content, $signature, $res, OPENSSL_ALGO_SHA256);
    if (PHP_VERSION_ID < 80000 && is_resource($res)) {
        @openssl_free_key($res);
    }
    return $ok ? base64_encode($signature) : false;
}

function netpay_alipay_verify($content, $sign, $publicKey)
{
    $pem = netpay_alipay_public_pem($publicKey);
    if ($pem === '' || $sign === '') {
        return false;
    }
    $pub = @openssl_pkey_get_public($pem);
    if ($pub === false && strpos($pem, 'CERTIFICATE') !== false) {
        $cert = @openssl_x509_read($pem);
        if ($cert !== false) {
            $pub = @openssl_pkey_get_public($cert);
        }
    }
    if ($pub === false) {
        return false;
    }
    $result = @openssl_verify($content, base64_decode($sign, true), $pub, OPENSSL_ALGO_SHA256);
    if (PHP_VERSION_ID < 80000 && is_resource($pub)) {
        @openssl_free_key($pub);
    }
    return intval($result) === 1;
}

/**
 * 生成支付宝支付表单
 */
function netpay_alipay_create($order)
{
    $config = netpay_load_config();
    if ($config['alipay_enable'] !== 'on') {
        return array('ok' => false, 'error' => '支付宝支付未开启');
    }
    if ($config['alipay_appid'] === '' || $config['alipay_private_key'] === '' || !netpay_url_is_valid($config['alipay_gateway'])) {
        return array('ok' => false, 'error' => '支付宝配置不完整');
    }
    $expireMinutes = max(1, intval(ceil(intval($config['pay_order_expire']) / 60)));
    $bizContent = array(
        'out_trade_no'    => $order['orderno'],
        'total_amount'    => number_format(floatval($order['amount']), 2, '.', ''),
        'subject'         => mb_substr($config['pay_subject'], 0, 60, 'UTF-8') . '-' . $order['orderno'],
        'product_code'    => 'QUICK_WAP_WAY',
        'quit_url'        => netpay_return_url('alipay'),
        'timeout_express' => $expireMinutes . 'm',
    );
    $params = array(
        'app_id'      => $config['alipay_appid'],
        'method'      => 'alipay.trade.wap.pay',
        'format'      => 'JSON',
        'charset'     => 'utf-8',
        'sign_type'   => 'RSA2',
        'timestamp'   => date('Y-m-d H:i:s'),
        'version'     => '1.0',
        'notify_url'  => netpay_notify_url('alipay'),
        'return_url'  => netpay_return_url('alipay'),
        'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );
    $sign = netpay_alipay_sign(netpay_alipay_sign_content($params), $config['alipay_private_key']);
    if ($sign === false) {
        netpay_log('支付宝签名失败', array('orderno' => $order['orderno']));
        return array('ok' => false, 'error' => '支付宝私钥格式不正确，签名失败');
    }
    $params['sign'] = $sign;
    return array('ok' => true, 'type' => 'form', 'html' => netpay_auto_form($config['alipay_gateway'], $params, 'POST'));
}

/**
 * 校验支付宝异步通知
 */
function netpay_alipay_verify_notify($post)
{
    $config = netpay_load_config();
    if (!is_array($post) || empty($post['sign']) || empty($post['out_trade_no'])) {
        return array('ok' => false, 'error' => '缺少必要参数');
    }
    if (empty($config['alipay_public_key'])) {
        return array('ok' => false, 'error' => '支付宝公钥未配置');
    }
    $params = $post;
    $sign = (string)$params['sign'];
    unset($params['sign'], $params['sign_type']);
    $content = netpay_alipay_sign_content($params);
    if (!netpay_alipay_verify($content, $sign, $config['alipay_public_key'])) {
        return array('ok' => false, 'error' => '支付宝签名验证失败');
    }
    if (isset($params['app_id']) && $config['alipay_appid'] !== '' && !hash_equals($config['alipay_appid'], (string)$params['app_id'])) {
        return array('ok' => false, 'error' => '支付宝 APPID 不匹配');
    }
    $tradeStatus = isset($params['trade_status']) ? (string)$params['trade_status'] : '';
    if (!in_array($tradeStatus, array('TRADE_SUCCESS', 'TRADE_FINISHED'), true)) {
        return array('ok' => false, 'error' => '交易未成功', 'ignore' => true);
    }
    return array(
        'ok'       => true,
        'orderno'  => (string)$params['out_trade_no'],
        'trade_no' => isset($params['trade_no']) ? (string)$params['trade_no'] : '',
        'amount'   => isset($params['total_amount']) ? floatval($params['total_amount']) : 0,
    );
}
