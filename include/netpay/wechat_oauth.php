<?php
/**
 * 微信网页授权回调（微信内 JSAPI 支付获取 openid）
 */
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once(dirname(dirname(__FILE__)) . '/common.php');
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/order.php');

function netpay_wx_oauth_fail($message, $target)
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>微信授权失败</title></head>'
        . '<body style="margin:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,\'PingFang SC\',sans-serif;color:#1f2937">'
        . '<div style="max-width:460px;margin:60px auto;background:#fff;border-radius:14px;padding:28px 24px;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.08)">'
        . '<p style="font-size:15px;line-height:24px;margin:0 0 18px;">' . netpay_html($message) . '</p>'
        . '<a href="' . netpay_html($target) . '" style="display:inline-block;padding:10px 24px;border-radius:8px;background:#07c160;color:#fff;text-decoration:none;font-size:14px;">返回充值页面</a>'
        . '</div></body></html>';
    exit;
}

$fallback = netpay_base_url() . '/member/index.php?action=netpay';
$uid = intval(isset($_SESSION['sea_user_id']) ? $_SESSION['sea_user_id'] : 0);
if ($uid <= 0) {
    netpay_wx_oauth_fail('登录状态已失效，请重新登录后支付。', netpay_base_url() . '/member/login.php');
}

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$state = isset($_GET['state']) ? trim((string)$_GET['state']) : '';
$expectState = isset($_SESSION['netpay_wx_oauth_state']) ? (string)$_SESSION['netpay_wx_oauth_state'] : '';
$orderno = isset($_SESSION['netpay_wx_oauth_orderno']) ? (string)$_SESSION['netpay_wx_oauth_orderno'] : '';
unset($_SESSION['netpay_wx_oauth_state'], $_SESSION['netpay_wx_oauth_orderno']);

if ($code === '' || $state === '' || $expectState === '' || !hash_equals($expectState, $state)) {
    netpay_wx_oauth_fail('微信授权已取消或校验失败，请重新发起支付。', $fallback);
}
if (!preg_match('/^[0-9]{6,32}$/', $orderno)) {
    netpay_wx_oauth_fail('支付订单信息缺失，请重新发起支付。', $fallback);
}
$order = netpay_get_order($orderno);
if (empty($order) || intval($order['uid']) !== $uid || intval($order['status']) !== 0) {
    netpay_wx_oauth_fail('支付订单不存在或已处理，请重新发起支付。', $fallback);
}

$config = netpay_load_config();
if ($config['wechat_enable'] !== 'on' || $config['wechat_appid'] === '' || $config['wechat_secret'] === '') {
    netpay_wx_oauth_fail('微信支付配置不完整，请联系管理员。', $fallback);
}

$url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query(array(
    'appid'      => $config['wechat_appid'],
    'secret'     => $config['wechat_secret'],
    'code'       => $code,
    'grant_type' => 'authorization_code',
));
$resp = netpay_http_request($url, null, array('Accept: application/json'), 10);
$data = $resp['ok'] ? json_decode($resp['body'], true) : false;
if (!is_array($data) || empty($data['openid'])) {
    netpay_wx_oauth_fail('微信授权失败，请重新发起支付。', $fallback);
}
if (!preg_match('/^[0-9A-Za-z_\-]{10,64}$/', (string)$data['openid'])) {
    netpay_wx_oauth_fail('微信授权数据异常，请重新发起支付。', $fallback);
}

$_SESSION['netpay_wx_openid'] = (string)$data['openid'];
header('Location: ' . $fallback . '&do=pay&orderno=' . urlencode($orderno), true, 302);
exit;
