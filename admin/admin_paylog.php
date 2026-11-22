<?php
require_once(dirname(__FILE__) . "/config.php");
require_once(sea_INC . "/netpay/order.php");
CheckPurview();

// 修改订单状态（已完成 / 已退款 / 待支付 / 未知）
if (isset($_POST['do']) && $_POST['do'] === 'setstatus') {
    $isAjax = (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    if ($isAjax) {
        // AJAX 请求使用 JSON 响应，CSRF 失败也返回 JSON
        $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        if (empty($_SESSION['admin_csrf_token']) || !is_string($token)
            || !hash_equals((string)$_SESSION['admin_csrf_token'], $token)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('ok' => false, 'message' => '请求已过期或非法提交，请刷新页面后重试'), JSON_UNESCAPED_UNICODE);
            exit();
        }
    } else {
        admin_csrf_check();
    }
    $oid = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $newStatus = isset($_POST['newstatus']) ? intval($_POST['newstatus']) : -1;
    $operator = isset($_SESSION['sea_admin_name']) ? (string)$_SESSION['sea_admin_name'] : '';
    $result = netpay_set_order_status($oid, $newStatus, $operator);
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'ok' => !empty($result['ok']),
            'message' => (string)$result['message'],
            'status' => $newStatus,
            'statusname' => netpay_status_name($newStatus),
            'statusclass' => in_array($newStatus, array(0, 1, 2, 4), true) ? 'paylog-s' . $newStatus : 'paylog-s-unknown',
        ), JSON_UNESCAPED_UNICODE);
        exit();
    }
    $back = 'admin_paylog.php';
    if (isset($_POST['back']) && preg_match('/^[0-9A-Za-z%._\-&=+]*$/', (string)$_POST['back'])) {
        $back .= '?' . (string)$_POST['back'];
    }
    ShowMsg($result['message'], $back);
    exit();
}

function paylog_valid_date($date)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $ts = strtotime($date . ' 00:00:00');
    return $ts !== false;
}

$rtype = isset($_GET['rtype']) ? intval($_GET['rtype']) : 0;
if (!in_array($rtype, array(0, 1, 2, 3, 4, 5, 6), true)) {
    $rtype = 0;
}
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
if (!in_array($status, array('', '0', '1', '2', '4', '9'), true)) {
    $status = '';
}
$channel = isset($_GET['channel']) ? strtolower(trim((string)$_GET['channel'])) : '';
if (!in_array($channel, array('', 'card', 'alipay', 'wechat', 'mazfu', 'usdt', 'sign', 'promo', 'reg'), true)) {
    $channel = '';
}
$uname = isset($_GET['uname']) ? trim((string)$_GET['uname']) : '';
if (mb_strlen($uname, 'UTF-8') > 50) {
    $uname = mb_substr($uname, 0, 50, 'UTF-8');
}
$startdate = isset($_GET['startdate']) ? trim((string)$_GET['startdate']) : '';
$enddate = isset($_GET['enddate']) ? trim((string)$_GET['enddate']) : '';
if ($startdate !== '' && !paylog_valid_date($startdate)) {
    $startdate = '';
}
if ($enddate !== '' && !paylog_valid_date($enddate)) {
    $enddate = '';
}

$filterWhere = '1=1';
$where = '1=1';
if ($rtype > 0) {
    $filterWhere .= " AND rtype={$rtype}";
}
if ($channel !== '') {
    $filterWhere .= " AND channel='" . addslashes($channel) . "'";
}
if ($uname !== '') {
    if (ctype_digit($uname)) {
        $filterWhere .= ' AND (uid=' . intval($uname) . " OR uname LIKE '%" . addslashes($uname) . "%')";
    } else {
        $filterWhere .= " AND uname LIKE '%" . addslashes($uname) . "%'";
    }
}
if ($startdate !== '') {
    $filterWhere .= ' AND addtime>=' . intval(strtotime($startdate . ' 00:00:00'));
}
if ($enddate !== '') {
    $filterWhere .= ' AND addtime<=' . intval(strtotime($enddate . ' 23:59:59'));
}
$where = $filterWhere;
if ($status === '9') {
    $where .= ' AND status NOT IN (0,1,4)';
} elseif ($status !== '') {
    $where .= ' AND status=' . intval($status);
}

$overall = netpay_summary(0);

$numPerPage = 20;
$page = isset($page) ? intval($page) : 1;
if ($page < 1) {
    $page = 1;
}
$rowTotal = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_paylog WHERE {$where}");
$TotalResult = is_array($rowTotal) ? intval($rowTotal['dd']) : 0;
$TotalPage = max(1, intval(ceil($TotalResult / $numPerPage)));
if ($page > $TotalPage) {
    $page = $TotalPage;
}
$limitstart = ($page - 1) * $numPerPage;

$rows = array();
$dsql->SetQuery("SELECT * FROM sea_paylog WHERE {$where} ORDER BY id DESC limit {$limitstart},{$numPerPage}");
$dsql->Execute('paylog_list');
while ($row = $dsql->GetArray('paylog_list')) {
    $rows[] = $row;
}

function paylog_amount_text($row)
{
    $currency = strtoupper($row['currency']);
    $amount = number_format(floatval($row['amount']), 2, '.', '');
    if ($currency === 'POINTS') {
        return $amount . ' 积分';
    }
    if ($currency === 'USDT') {
        return $amount . ' USDT';
    }
    if (floatval($amount) == 0) {
        return '-';
    }
    return '￥' . $amount;
}

$queryParams = array(
    'rtype'     => $rtype,
    'status'    => $status,
    'channel'   => $channel,
    'uname'     => $uname,
    'startdate' => $startdate,
    'enddate'   => $enddate,
);
$queryString = http_build_query($queryParams);
$pageBase = 'admin_paylog.php?' . ($queryString !== '' ? $queryString . '&' : '');

include(sea_ADMIN . '/templets/admin_paylog.htm');
exit();
