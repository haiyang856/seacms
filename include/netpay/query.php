<?php
/**
 * 订单状态查询（仅供当前登录会员轮询自己的订单）
 */
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once(dirname(dirname(__FILE__)) . '/common.php');
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once(dirname(__FILE__) . '/order.php');

header('Content-Type: application/json; charset=utf-8');
$uid = intval(isset($_SESSION['sea_user_id']) ? $_SESSION['sea_user_id'] : 0);
$orderno = isset($_GET['orderno']) ? trim((string)$_GET['orderno']) : '';
if ($uid <= 0 || $orderno === '' || !preg_match('/^[0-9A-Za-z]{6,64}$/', $orderno)) {
    echo json_encode(array('ok' => false, 'status' => -1));
    exit;
}
$order = netpay_get_order($orderno);
if (empty($order) || intval($order['uid']) !== $uid) {
    echo json_encode(array('ok' => false, 'status' => -1));
    exit;
}
echo json_encode(array(
    'ok'     => true,
    'status' => intval($order['status']),
    'points' => intval($order['points']),
));
