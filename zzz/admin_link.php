<?php 
require_once(dirname(__FILE__)."/config.php");
CheckPurview();
if(empty($action))
{
	$action = '';
}
$id = empty($id) ? 0 : intval($id);

if($action=="add")
{
	admin_csrf_check();
	if(empty($webname))
	{
		ShowMsg("网站名称没有填写，请返回检查","-1");
		exit();
	}
	if(empty($url))
	{
		ShowMsg("网站地址没有填写，请返回检查","-1");
		exit();
	}
	if(!preg_match('/^https?:\/\//',$url))
	{
		ShowMsg("网站地址必须以 http:// 或 https:// 开头，请返回检查","-1");
		exit();
	}
	if(empty($sortrank))
		{
		$trow = $dsql->GetOne("select max(sortrank)+1 as dd from sea_flink");
		$trow=is_array($trow)?$trow:array(); $sortrank = $trow['dd'];
		}
	if (!is_numeric($sortrank)) $sortrank=1;
	if($v_type=="font") $logo="";
	if(!preg_match('/^[A-Za-z0-9_.\/\-]*$/',$logo))
	{
		ShowMsg("LOGO地址含有非法字符，请返回检查","-1");
		exit();
	}
	$webname = addslashes(htmlspecialchars(stripslashes($webname),ENT_QUOTES));
	$msg = addslashes(htmlspecialchars(stripslashes($msg),ENT_QUOTES));
	$url = addslashes($url);
	$logo = addslashes($logo);
	$sortrank = intval($sortrank);
	$dtime = time();
	$query = "Insert Into `sea_flink`(sortrank,url,webname,logo,msg,email,dtime,ischeck) Values('$sortrank','$url','$webname','$logo','$msg','$email','$dtime','$ischeck'); ";
	$rs = $dsql->ExecuteNoneQuery($query);
	if($rs)
	{
		ShowMsg("成功增加一个链接!","admin_link.php");
		exit();
	}
	else
	{
		ShowMsg("增加链接时出错，请向官方反馈，原因：".$dsql->GetError(),"javascript:;");
		exit();
	}
}
elseif($action=="save")
{
	admin_csrf_check();
	$row=$dsql->GetOne("select id from `sea_flink` where id='$id'");
	if(!is_array($row))
	{
		ShowMsg("需要修改的链接不存在！","-1");
		exit();
	}
	if(empty($webname))
	{
		ShowMsg("网站名称没有填写，请返回检查","-1");
		exit();
	}
	if(empty($url))
	{
		ShowMsg("网站地址没有填写，请返回检查","-1");
		exit();
	}
	if(!preg_match('/^https?:\/\//',$url))
	{
		ShowMsg("网站地址必须以 http:// 或 https:// 开头，请返回检查","-1");
		exit();
	}
	if(empty($sortrank))
		{
		$trow = $dsql->GetOne("select max(sortrank)+1 as dd from sea_flink");
		$trow=is_array($trow)?$trow:array(); $sortrank = $trow['dd'];
		}
	if(!is_numeric($sortrank)) $sortrank=1;
	if($v_type=="font") $logo="";
	if(!preg_match('/^[A-Za-z0-9_.\/\-]*$/',$logo))
	{
		ShowMsg("LOGO地址含有非法字符，请返回检查","-1");
		exit();
	}
	$webname = addslashes(htmlspecialchars(stripslashes($webname),ENT_QUOTES));
	$msg = addslashes(htmlspecialchars(stripslashes($msg),ENT_QUOTES));
	$url = addslashes($url);
	$logo = addslashes($logo);
	$sortrank = intval($sortrank);
	$query = "Update `sea_flink` set sortrank='$sortrank',url='$url',webname='$webname',logo='$logo',msg='$msg',ischeck='$ischeck',email='$email' where id='$id' ";
	$dsql->ExecuteNoneQuery($query);
	ShowMsg("成功更改一个链接！","admin_link.php");
	exit();
}
elseif($action=="last")
{
	$row=$dsql->GetOne("select sortrank from `sea_flink` where id='$id'");
	if(!is_array($row))
	{
		ShowMsg("需要操作的链接不存在！","-1");
		exit();
	}
	$cur=$row['sortrank'];
	//与相邻链接交换排序值，避免产生重复排序
	$nb=$dsql->GetOne("select id,sortrank from `sea_flink` where sortrank<'$cur' order by sortrank desc limit 1");
	if(is_array($nb))
	{
		$dsql->ExecuteNoneQuery("update `sea_flink` set sortrank='".$nb['sortrank']."' where id='$id'");
		$dsql->ExecuteNoneQuery("update `sea_flink` set sortrank='$cur' where id='".$nb['id']."'");
	}
	header("Location:admin_link.php?id=$id");
	exit;
}
elseif($action=="next")
{
	$row=$dsql->GetOne("select sortrank from `sea_flink` where id='$id'");
	if(!is_array($row))
	{
		ShowMsg("需要操作的链接不存在！","-1");
		exit();
	}
	$cur=$row['sortrank'];
	$nb=$dsql->GetOne("select id,sortrank from `sea_flink` where sortrank>'$cur' order by sortrank asc limit 1");
	if(is_array($nb))
	{
		$dsql->ExecuteNoneQuery("update `sea_flink` set sortrank='".$nb['sortrank']."' where id='$id'");
		$dsql->ExecuteNoneQuery("update `sea_flink` set sortrank='$cur' where id='".$nb['id']."'");
	}
	header("Location:admin_link.php?id=$id");
	exit;
}
elseif($action=="del")
{
	$row=$dsql->GetOne("select id from `sea_flink` where id='$id'");
	if(!is_array($row))
	{
		ShowMsg("需要删除的链接不存在！","-1");
		exit();
	}
	$dsql->ExecuteNoneQuery("delete from `sea_flink` where id='$id'");
	header("Location:admin_link.php?id=$id");
	exit;
}
elseif($action=="delall")
{
	admin_csrf_check();
	$e_id = (isset($e_id) && is_array($e_id)) ? array_filter(array_map('intval',$e_id)) : array();
	if(empty($e_id))
	{
		ShowMsg("请选择需要删除的链接","-1");
		exit();
	}
	$ids = implode(',',$e_id);
	$dsql->ExecuteNoneQuery("delete from `sea_flink` where id in ($ids)");
	header("Location:admin_link.php");
	exit;
}
elseif($action=="editall")
{
	admin_csrf_check();
	$e_id = (isset($e_id) && is_array($e_id)) ? array_filter(array_map('intval',$e_id)) : array();
	if(empty($e_id))
	{
		ShowMsg("请选择需要修改的链接","-1");
		exit();
	}
	//先全量校验，全部通过后再统一更新，避免部分成功部分失败
	$updates = array();
	foreach($e_id as $eid)
	{
		$webname = isset($_POST["webname$eid"]) ? $_POST["webname$eid"] : '';
		$url = isset($_POST["url$eid"]) ? $_POST["url$eid"] : '';
		$sortrank = isset($_POST["sortrank$eid"]) ? $_POST["sortrank$eid"] : '';
		if(empty($webname))
		{
			ShowMsg("网站名称没有填写，请返回检查","-1");
			exit();
		}
		if(empty($url))
		{
			ShowMsg("网站地址没有填写，请返回检查","-1");
			exit();
		}
		if(!preg_match('/^https?:\/\//',$url))
		{
			ShowMsg("网站地址必须以 http:// 或 https:// 开头，请返回检查","-1");
			exit();
		}
		if(empty($sortrank))
		{
			$trow = $dsql->GetOne("select max(sortrank)+1 as dd from sea_flink");
			$trow=is_array($trow)?$trow:array(); $sortrank = $trow['dd'];
		}
		if(!is_numeric($sortrank)) $sortrank=1;
		$webname = addslashes(htmlspecialchars(stripslashes($webname),ENT_QUOTES));
		$url = addslashes($url);
		$sortrank = intval($sortrank);
		$updates[] = "update sea_flink set webname='$webname',url='$url',sortrank='$sortrank' where id=$eid";
	}
	foreach($updates as $query)
	{
		$dsql->ExecuteNoneQuery($query);
	}
	header("Location:admin_link.php");
	exit;
}
else
{
	include(sea_ADMIN.'/templets/admin_link.htm');
	exit();
}
?>