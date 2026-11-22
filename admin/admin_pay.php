<?php
require_once(dirname(__FILE__) . "/config.php");
require_once(sea_DATA . "/config.user.inc.php");
CheckPurview();

$action = isset($action) ? (string)$action : '';
$ckey = isset($_GET['ckey']) ? trim((string)$_GET['ckey']) : '';
$climit = isset($_GET['climit']) ? trim((string)$_GET['climit']) : '';
$cstatus = isset($_GET['cstatus']) ? trim((string)$_GET['cstatus']) : '';

if (mb_strlen($ckey, 'UTF-8') > 60) {
    $ckey = mb_substr($ckey, 0, 60, 'UTF-8');
}
if ($cstatus !== '0' && $cstatus !== '1') {
    $cstatus = '';
}
if ($climit !== '' && $climit !== 'all' && !ctype_digit($climit)) {
    $climit = '';
}

if ($action === 'add') {
    admin_csrf_check();
    $num = isset($_POST['num']) ? intval($_POST['num']) : 0;
    $face = isset($_POST['limit']) ? intval($_POST['limit']) : 0;
    if ($num < 1 || $num > 200) {
        ShowMsg("单次生成数量需在 1 - 200 之间", "admin_pay.php");
        exit;
    }
    if ($face < 1 || $face > 1000000) {
        ShowMsg("充值卡面值不合法", "admin_pay.php");
        exit;
    }
    for ($i = 0; $i < $num; $i++) {
        $key = $face . "-" . md5(uniqid((string)mt_rand(), true));
        $keyEsc = addslashes($key);
        $dsql->ExecuteNoneQuery("INSERT INTO `sea_cck`(ckey,climit,maketime,usetime,uname,status,rtype) VALUES ('{$keyEsc}',{$face},NOW(),NULL,NULL,'0',1)");
    }
    ShowMsg("执行成功", "admin_pay.php");
    exit;
} elseif ($action === 'del') {
    $token = isset($_GET['csrf_token']) ? (string)$_GET['csrf_token'] : '';
    if (empty($_SESSION['admin_csrf_token']) || !hash_equals((string)$_SESSION['admin_csrf_token'], $token)) {
        ShowMsg("请求已过期或非法提交（CSRF校验失败），请返回重试！", "admin_pay.php");
        exit;
    }
    $id = isset($id) ? intval($id) : 0;
    if ($id > 0) {
        $dsql->ExecuteNoneQuery("DELETE FROM `sea_cck` WHERE id={$id}");
    }
    ShowMsg("删除成功", "admin_pay.php");
    exit;
} elseif ($action === 'delall') {
    admin_csrf_check();
    $ids = array();
    if (isset($e_id) && is_array($e_id)) {
        foreach ($e_id as $v) {
            $v = intval($v);
            if ($v > 0) {
                $ids[] = $v;
            }
        }
    }
    if (empty($ids)) {
        ShowMsg("请选择需要删除的数据", "admin_pay.php");
        exit();
    }
    $dsql->ExecuteNoneQuery("DELETE FROM sea_cck WHERE id IN(" . implode(',', $ids) . ")");
    ShowMsg("批量删除成功", "admin_pay.php");
    exit();
}

include(sea_ADMIN . '/templets/admin_pay.htm');
exit();
