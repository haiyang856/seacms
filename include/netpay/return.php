<?php
/**
 * 在线充值支付完成同步跳转
 */
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once(dirname(dirname(__FILE__)) . '/common.php');
require_once(dirname(__FILE__) . '/config.php');

$channel = isset($_GET['channel']) ? strtolower(trim((string)$_GET['channel'])) : '';
$target = netpay_base_url() . '/member/index.php?action=buy3&paydone=1';
header('Location: ' . $target, true, 302);
exit;
