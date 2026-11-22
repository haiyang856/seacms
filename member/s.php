<?php
session_start();

error_reporting(0);
require_once(dirname(__DIR__).'/include/gpc.filter.php');
  
require_once(dirname(__DIR__)."/include/common.php");
require_once(sea_INC."/main.class.php");
require_once(sea_INC."/netpay/order.php");

if($cfg_spoints==0){showmsg('未开启签到功能', 'index.php');exit;}

$u=addslashes($_SESSION['sea_user_id'] ?? '');
if(empty($u) OR !is_numeric($u)){showmsg('无法获取目标用户ID', 'index.php');exit;}

$row = $dsql->GetOne("Select stime from sea_member where id='$u'");

$nowtime=time();

$row=is_array($row)?$row:array(); $lasttime=$row['stime'];

if($nowtime-$lasttime > 86400 )
{
	$dsql->ExecuteNoneQuery("Update sea_member set stime = $nowtime  where id='$u'");
	$sql="Update sea_member set points = points+$cfg_spoints where id=$u";
	$dsql->ExecuteNoneQuery("$sql");
	netpay_log_points($u, 4, 'sign', intval($cfg_spoints), '每日签到奖励');
	showmsg('签到成功！', 'index.php');exit;
}
else
{
	showmsg('已经签到！', 'index.php');exit;
}
?>