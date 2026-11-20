<?php 
require_once(dirname(__FILE__)."/config.php");
CheckPurview();
if(empty($action))
{
	$action = '';
}

$id = empty($id) ? 0 : intval($id);
$whereStr = '';
if(!isset($page)) $page=1;
if(!isset($TotalPage)) $TotalPage=0;
if(!isset($TotalResult)) $TotalResult=0;
if(!isset($numPerPage)) $numPerPage=20;
if(!isset($keyword)) $keyword='';

if($action=="editcomsave")
{
	$row = $dsql->GetOne("select id from sea_comment where id=$id and m_type=1");
	if(!is_array($row))
	{
		ShowMsg("需要编辑的评论不存在！","-1");
		exit();
	}
	$username = addslashes(htmlspecialchars(stripslashes($username),ENT_QUOTES));
	$msg = stripslashes($msg);
	$msg = htmlspecialchars($msg,ENT_QUOTES);
	$msg = str_replace(array("&lt;br/&gt;","&lt;br /&gt;"),"<br/>",$msg);
	$msg = cn_substr(addslashes($msg),2500);
	$adminmsg = trim($adminmsg);
	if($adminmsg!="")
	{
		$adminmsg = cn_substrR($adminmsg,1500);
		$adminmsg = str_replace("<","&lt;",$adminmsg);
		$adminmsg = str_replace(">","&gt;",$adminmsg);
		$adminmsg = str_replace("  ","&nbsp;&nbsp;",$adminmsg);
		$adminmsg = str_replace("\r\n","<br/>\n",$adminmsg);
		$msg = $msg."<br/>\n"."<span>管理员回复： $adminmsg</span>\n";
	}
	delcommentcache($id);
	$query = "update `sea_comment` set username='$username',msg='$msg',ischeck=1 where id=$id and m_type=1";
	$dsql->ExecuteNoneQuery($query);
	ShowMsg("成功回复一则评论！","admin_comment_news.php");
	exit();
}
elseif($action=="delcomment")
{
	$row = $dsql->GetOne("select id from sea_comment where id=$id and m_type=1");
	if(!is_array($row))
	{
		ShowMsg("需要删除的评论不存在！","-1");
		exit();
	}
	delcommentcache($id);
	$dsql->ExecuteNoneQuery("delete from sea_comment where id=".$id." and m_type=1");
	ShowMsg("成功删除一则评论！","admin_comment_news.php");
	exit();
}
elseif($action=="delallcomment")
{
	$e_id = (isset($e_id) && is_array($e_id)) ? array_filter(array_map('intval',$e_id)) : array();
	if(empty($e_id))
	{
		ShowMsg("请选择需要删除的评论","admin_comment_news.php");
		exit();
	}
	$ids = implode(',',$e_id);
	delcommentcache($ids);
	$dsql->ExecuteNoneQuery("delete from sea_comment where id in(".$ids.") and m_type=1");
	ShowMsg("成功删除所选评论！","admin_comment_news.php");
	exit();
}
elseif($action=="deltotalcomment")
{
	@cache_clear(sea_ROOT.'/data/cache/review/1');
	$dsql->ExecuteNoneQuery("delete from sea_comment where m_type=1");
	ShowMsg("成功删除所有评论！","admin_comment_news.php");
	exit();
}
elseif($action=="checkcomment")
{
	$e_id = (isset($e_id) && is_array($e_id)) ? array_filter(array_map('intval',$e_id)) : array();
	if(empty($e_id))
	{
		ShowMsg("请选择需要审核的评论","admin_comment_news.php");
		exit();
	}
	$ids = implode(',',$e_id);
	$dsql->ExecuteNoneQuery("update sea_comment set ischeck=1 where id in(".$ids.") and m_type=1");
	ShowMsg("成功审核所选评论！","admin_comment_news.php");
	exit();
}
elseif($action=="delreporterror")
{
	$row = $dsql->GetOne("select id from sea_erradd where id=".$id);
	if(!is_array($row))
	{
		ShowMsg("需要删除的报错信息不存在！","-1");
		exit();
	}
	$dsql->ExecuteNoneQuery("delete from sea_erradd where id=".$id);
	ShowMsg("成功删除一条报错信息！","admin_comment_news.php");
	exit();
}
elseif($action=="delallreporterror")
{
	$e_id = (isset($e_id) && is_array($e_id)) ? array_filter(array_map('intval',$e_id)) : array();
	if(empty($e_id))
	{
		ShowMsg("请选择需要删除的信息","admin_comment_news.php");
		exit();
	}
	$ids = implode(',',$e_id);
	$dsql->ExecuteNoneQuery("delete from sea_erradd where id in(".$ids.")");
	ShowMsg("成功删除所选信息！","admin_comment_news.php");
	exit();
}
else
{
	include(sea_ADMIN.'/templets/admin_comment_news.htm');
	exit();
}

function delcommentcache($id)
{
	global $dsql;
	$dsql->setQuery("select v_id from sea_comment where id in (".$id.")");
	$dsql->Execute("delcommentcache");
	while($row = $dsql->GetArray("delcommentcache"))
	{
		if(file_exists(sea_DATA.'/cache/review/1/'.$row['v_id'].'.js'))
		{
			delfile(sea_DATA.'/cache/review/1/'.$row['v_id'].'.js');
		}
	}
}

function IsCheck($st)
{
	return $st==1 ? "[已审核]" : "<font color='red'>[未审核]</font>";
}
?>