<?php
/**
 * 在线充值支付回调统一入口
 * 访问方式：/include/netpay/notify.php?channel=alipay|wechat|mazfu|usdt
 */
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once(dirname(dirname(__FILE__)) . '/common.php');
require_once(dirname(__FILE__) . '/order.php');

$channel = isset($_GET['channel']) ? strtolower(trim((string)$_GET['channel'])) : '';
if (!in_array($channel, array('alipay', 'wechat', 'mazfu', 'usdt'), true)) {
    http_response_code(400);
    echo 'invalid channel';
    exit;
}

/**
 * 统一处理验签结果
 */
function netpay_notify_handle($result, $channel)
{
    if (!empty($result['ok'])) {
        $credit = netpay_credit_order($result['orderno'], $result['trade_no'], $result['amount'], $channel);
        if ($credit['ok']) {
            return true;
        }
        netpay_log('回调入账失败', array('channel' => $channel, 'orderno' => $result['orderno'], 'message' => $credit['message']));
        return false;
    }
    if (!empty($result['ignore'])) {
        return true;
    }
    netpay_log('回调验签失败', array('channel' => $channel, 'error' => $result['error']));
    return false;
}

if ($channel === 'alipay') {
    header('Content-Type: text/plain; charset=utf-8');
    require_once(dirname(__FILE__) . '/alipay.php');
    $result = netpay_alipay_verify_notify($_POST);
    echo netpay_notify_handle($result, 'alipay') ? 'success' : 'fail';
    exit;
}

if ($channel === 'wechat') {
    header('Content-Type: text/xml; charset=utf-8');
    require_once(dirname(__FILE__) . '/wechat.php');
    $raw = file_get_contents('php://input');
    $result = netpay_wechat_verify_notify($raw);
    echo netpay_wechat_reply(netpay_notify_handle($result, 'wechat'), isset($result['error']) ? $result['error'] : '');
    exit;
}

if ($channel === 'mazfu') {
    header('Content-Type: text/plain; charset=utf-8');
    require_once(dirname(__FILE__) . '/mazfu.php');
    $input = array_merge($_GET, $_POST);
    $result = netpay_mazfu_verify_notify($input);
    echo netpay_notify_handle($result, 'mazfu') ? 'success' : 'fail';
    exit;
}

if ($channel === 'usdt') {
    header('Content-Type: text/plain; charset=utf-8');
    require_once(dirname(__FILE__) . '/usdt.php');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        netpay_log('USDT 回调 JSON 解析失败');
        echo 'fail';
        exit;
    }
    $result = netpay_usdt_verify_notify($data);
    $ok = netpay_notify_handle($result, 'usdt');
    echo $ok ? 'ok' : 'fail';
    exit;
}
