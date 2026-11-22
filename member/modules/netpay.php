<?php
/**
 * 用户中心 - 在线充值
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}
require_once(sea_INC . '/netpay/order.php');

/**
 * 输出支付跳转内容（302 / 自动提交表单 / 扫码页或 JSAPI 页）
 */
function netpay_render_payment($pay)
{
    if (empty($pay['ok'])) {
        ShowMsg(isset($pay['error']) ? $pay['error'] : '发起支付失败', 'index.php?action=netpay');
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    if ($pay['type'] === 'redirect') {
        header('Location: ' . $pay['url'], true, 302);
        exit;
    }
    if ($pay['type'] === 'form') {
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>正在跳转支付</title></head><body>'
            . $pay['html'] . '</body></html>';
        exit;
    }
    if (!empty($pay['html'])) {
        echo $pay['html'];
        exit;
    }
    ShowMsg('支付参数异常，请稍后再试', 'index.php?action=netpay');
    exit;
}

//发起支付（do 参数位于表单 action 的查询串中）
$netpay_do = isset($_POST['do']) ? $_POST['do'] : (isset($_GET['do']) ? $_GET['do'] : '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $netpay_do === 'create') {
    member_csrf_check();
    $payment = isset($_POST['payment']) ? strtolower(trim((string)$_POST['payment'])) : '';
    $allowed = array();
    foreach (netpay_available_channels() as $item) {
        $value = $item['channel'];
        if ($item['channel'] === 'mazfu') {
            $value = 'mazfu_' . $item['sub'];
        }
        $allowed[$value] = $item;
    }
    if (!isset($allowed[$payment])) {
        ShowMsg('该支付方式当前不可用，请重新选择', 'index.php?action=netpay');
        exit;
    }
    $channel = $allowed[$payment]['channel'];
    $sub = isset($allowed[$payment]['sub']) ? $allowed[$payment]['sub'] : '';
    $currency = $channel === 'usdt' ? 'USDT' : 'CNY';
    $amount = isset($_POST['amount']) ? trim((string)$_POST['amount']) : '';

    $created = netpay_create_order($uid, $_SESSION['sea_user_name'], $amount, $currency, $channel, array('sub' => $sub));
    if (!$created['ok']) {
        ShowMsg($created['error'], 'index.php?action=netpay');
        exit;
    }
    $order = $created['order'];
    $pay = netpay_start_payment($order);
    if (empty($pay['ok'])) {
        netpay_close_order($order['orderno'], '发起支付失败');
        ShowMsg(isset($pay['error']) ? $pay['error'] : '发起支付失败', 'index.php?action=netpay');
        exit;
    }
    netpay_render_payment($pay);
}

//继续支付（微信网页授权回调后按原订单重新发起支付）
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $netpay_do === 'pay') {
    $orderno = isset($_GET['orderno']) ? trim((string)$_GET['orderno']) : '';
    if (!preg_match('/^[0-9]{6,32}$/', $orderno)) {
        ShowMsg('订单号格式不正确', 'index.php?action=netpay');
        exit;
    }
    $order = netpay_get_order($orderno);
    if (empty($order) || intval($order['uid']) !== intval($uid)) {
        ShowMsg('订单不存在', 'index.php?action=netpay');
        exit;
    }
    if (intval($order['status']) === 1) {
        header('Location: index.php?action=buy3&paydone=1', true, 302);
        exit;
    }
    if (intval($order['status']) !== 0) {
        ShowMsg('订单已关闭，请重新下单', 'index.php?action=netpay');
        exit;
    }
    if (intval($order['addtime']) + max(60, intval(netpay_cfg('pay_order_expire', 900))) < time()) {
        netpay_close_order($order['orderno'], '订单超时');
        ShowMsg('订单已超时，请重新下单', 'index.php?action=netpay');
        exit;
    }
    $pay = netpay_start_payment($order);
    if (empty($pay['ok'])) {
        ShowMsg(isset($pay['error']) ? $pay['error'] : '发起支付失败', 'index.php?action=netpay');
        exit;
    }
    netpay_render_payment($pay);
}

$available = netpay_available_channels();
$channelsHtml = '';
foreach ($available as $index => $item) {
    $value = $item['channel'];
    if ($item['channel'] === 'mazfu') {
        $value = 'mazfu_' . $item['sub'];
    }
    $checked = $index === 0 ? ' checked' : '';
    $channelsHtml .= '<label class="netpay-channel">'
        . '<input type="radio" name="payment" value="' . member_e($value) . '" data-currency="' . member_e($item['currency']) . '"' . $checked . ' />'
        . '<span class="np-name">' . member_e($item['name']) . '</span>'
        . '<span class="np-desc">' . member_e($item['desc']) . '</span>'
        . '</label>';
}
$presetsHtml = '';
$min = floatval(netpay_cfg('pay_min_money', 1));
$max = floatval(netpay_cfg('pay_max_money', 5000));
foreach (array(10, 50, 100, 200, 500) as $preset) {
    if ($preset >= $min && $preset <= $max) {
        $presetsHtml .= '<button type="button" class="netpay-preset" data-amount="' . $preset . '">' . $preset . '</button>';
    }
}

member_sets(array(
    'pay_cny_points'  => rtrim(rtrim(number_format(floatval(netpay_cfg('pay_cny_points')), 2, '.', ''), '0'), '.'),
    'pay_usdt_points' => rtrim(rtrim(number_format(floatval(netpay_cfg('pay_usdt_points')), 2, '.', ''), '0'), '.'),
    'pay_min_money'   => netpay_amount_plain($min),
    'pay_max_money'   => netpay_amount_plain($max),
    'pay_channels'    => $channelsHtml,
    'pay_presets'     => $presetsHtml,
    'pay_disabled'    => empty($available) ? 'disabled' : '',
    'pay_empty_style' => empty($available) ? '' : 'display:none;',
    'pay_form_style'  => empty($available) ? 'display:none;' : '',
));

member_render('netpay.html');
