<?php 
require_once(dirname(__FILE__)."/config.php");
require_once(sea_INC."/charset.func.php");


CheckPurview();
if(empty($action))
{
	$action = '';
}
global $cfg_ismakeplay,$cfg_alertwinw,$cfg_alertwinh;
$m_file = sea_ROOT."/js/play.js";
$playerKindsfile = sea_DATA."/admin/playerKinds.xml";
if($action=="edit")
{
	$fp = fopen($m_file,'r');
	$player = fread($fp,filesize($m_file));
	fclose($fp);
	$player=preg_replace("/playerw='(.*?)';/is","playerw='".$playerwidth."';",$player);
	$player=preg_replace("/playerh='(.*?)';/is","playerh='".$playerheight."';",$player);
	$player=preg_replace("/mplayerw='(.*?)';/is","mplayerw='".$mplayerwidth."';",$player);
	$player=preg_replace("/mplayerh='(.*?)';/is","mplayerh='".$mplayerheight."';",$player);
	$player=preg_replace("/adsPage=(.*?)\";/is","adsPage=\"".$adbeforeplay."\";",$player);
	$player=preg_replace("/adsTime=(\d+);/is","adsTime=".$adtimebeforeplay.";",$player);
	$player=preg_replace("/jxAname=(.*?)\";/is","jxAname=\"".$jxAname."\";",$player);
	$player=preg_replace("/jxBname=(.*?)\";/is","jxBname=\"".$jxBname."\";",$player);
	$player=preg_replace("/jxCname=(.*?)\";/is","jxCname=\"".$jxCname."\";",$player);
	$player=preg_replace("/jxDname=(.*?)\";/is","jxDname=\"".$jxDname."\";",$player);
	$player=preg_replace("/jxEname=(.*?)\";/is","jxEname=\"".$jxEname."\";",$player);
	$player=preg_replace("/jxFname=(.*?)\";/is","jxFname=\"".$jxFname."\";",$player);
	$player=preg_replace("/jxGname=(.*?)\";/is","jxGname=\"".$jxGname."\";",$player);
	$player=preg_replace("/jxHname=(.*?)\";/is","jxHname=\"".$jxHname."\";",$player);
	$player=preg_replace("/jxIname=(.*?)\";/is","jxIname=\"".$jxIname."\";",$player);
	$player=preg_replace("/jxAapi=(.*?)\";/is","jxAapi=\"".$jxAapi."\";",$player);
	$player=preg_replace("/jxBapi=(.*?)\";/is","jxBapi=\"".$jxBapi."\";",$player);
	$player=preg_replace("/jxCapi=(.*?)\";/is","jxCapi=\"".$jxCapi."\";",$player);
	$player=preg_replace("/jxDapi=(.*?)\";/is","jxDapi=\"".$jxDapi."\";",$player);
	$player=preg_replace("/jxEapi=(.*?)\";/is","jxEapi=\"".$jxEapi."\";",$player);
	$player=preg_replace("/jxFapi=(.*?)\";/is","jxFapi=\"".$jxFapi."\";",$player);
	$player=preg_replace("/jxGapi=(.*?)\";/is","jxGapi=\"".$jxGapi."\";",$player);
	$player=preg_replace("/jxHapi=(.*?)\";/is","jxHapi=\"".$jxHapi."\";",$player);
	$player=preg_replace("/jxIapi=(.*?)\";/is","jxIapi=\"".$jxIapi."\";",$player);
	$player=preg_replace("/forcejx=(.*?)\";/is","forcejx=\"".$forcejx."\";",$player);
	$player=preg_replace("/unforcejx=(.*?)\";/is","unforcejx=\"".$unforcejx."\";",$player);
	$fp = fopen($m_file,'w');
	flock($fp,3);
	fwrite($fp,$player);
	fclose($fp);
	ShowMsg("成功保存设置!","admin_player.php");
	exit;
}
elseif($action=="save")
{
	$e_id = (isset($e_id) && is_array($e_id)) ? array_values(array_filter(array_map('intval',$e_id))) : array();
	if(empty($e_id))
	{
		ShowMsg("请选择要修改的项目","-1");
		exit();
	}
	$xml = simplexml_load_file($playerKindsfile);
	if(!$xml){$xml = simplexml_load_string(file_get_contents($playerKindsfile));}
	$i = 0;
	$found = 0;
	foreach($xml as $player){
		$i++;
		if(in_array($i,$e_id)){
			if(!isset(${'sort'.$i}) || !isset(${'postfix'.$i}) || !isset(${'flag'.$i}) || !isset(${'info'.$i}))
			{
				ShowMsg("提交数据不完整，请返回重试！","-1");
				exit();
			}
			$postfix = stripslashes(${'postfix'.$i});
			if(!preg_match('/^[0-9a-zA-Z_\-]+$/',$postfix))
			{
				ShowMsg("后缀含有非法字符，请返回检查！","-1");
				exit();
			}
			$player['sort']=intval(${'sort'.$i});
			$player['postfix']=$postfix;
			$player['flag']=gb2utf8(stripslashes(${'flag'.$i}));
			$player->intro=gb2utf8(stripslashes(${'info'.$i}));
			$found++;
			}
	}
	if($found==0)
	{
		ShowMsg("所选播放来源不存在！","-1");
		exit();
	}
	$xml->asXML($playerKindsfile);
	ShowMsg("成功保存设置!","admin_player.php?action=boardsource");
	exit;
}
elseif($action=="boardsource")
{
	include(sea_ADMIN.'/templets/admin_player.htm');
	exit();
}
elseif($action=="modifysourceban")
{
	$id = intval($id);
	$xml = simplexml_load_file($playerKindsfile);
	if(!$xml){$xml = simplexml_load_string(file_get_contents($playerKindsfile));}
	$i=0;
	$found=false;
	foreach($xml as $player){
		$i++;
		if($i==$id){
			if($player['open']==0)
			$player['open']=1;
			else
			$player['open']=0;
			$found=true;
			}
		}
	if(!$found)
	{
		ShowMsg("需要操作的播放来源不存在！","-1");
		exit();
	}
	$xml->asXML($playerKindsfile);
	header('Location: admin_player.php?action=boardsource');
	exit();
}
elseif($action=="modifysource")
{
	$id = intval($id);
	$xml = simplexml_load_file($playerKindsfile);
	if(!$xml){$xml = simplexml_load_string(file_get_contents($playerKindsfile));}
	$i=0;
	$found=false;
	foreach($xml as $player){
		$i++;
		if($i==$id){
			$postfix = stripslashes($postfix);
			if(!preg_match('/^[0-9a-zA-Z_\-]+$/',$postfix))
			{
				ShowMsg("后缀含有非法字符，请返回检查！","-1");
				exit();
			}
			$player['sort']=intval($sort);
			$player['postfix']=$postfix;
			$player->intro=gb2utf8(stripslashes($info));
			$found=true;
			}
	}
	if(!$found)
	{
		ShowMsg("需要修改的播放来源不存在！","-1");
		exit();
	}
	$xml->asXML($playerKindsfile);
	echo "<script>alert('修改成功！');</script>";
	exit();
}
elseif($action=="addnew")
{
	//add new
	$playername=isset($_POST['playername'])?$_POST['playername']:'';
	$info=isset($_POST['info'])?$_POST['info']:'';
	$order=isset($_POST['order'])?intval($_POST['order']):0;
	$trail=isset($_POST['trail'])?stripslashes($_POST['trail']):'';
	if($playername==''||$trail==''||!isset($_POST['order'])||$_POST['order']=='')
	{
		ShowMsg("请输入播放器名字，后缀，排序。","-1");
		exit();
	}
	if(!preg_match('/^[0-9a-zA-Z_\-]+$/',$trail))
	{
		ShowMsg("后缀含有非法字符，只允许字母、数字、下划线和横线！","-1");
		exit();
	}
	$defaultfolder="../js/player";
	if(empty($filedir)) $filedir=$defaultfolder;
	if($filedir!=$defaultfolder){
		ShowMsg("只能把模板添加在{$defaultfolder}文件夹","admin_player.php?action=boardsource");
		exit;
	}
	if(file_exists($filedir."/".$trail.".html")){
		ShowMsg("已存在该文件请更换名称","-1");
		exit;
	}
	//先创建模板文件，成功后再写入XML，避免产生孤儿条目
	$content = isset($content)?$content:'';
	createTextFile($content,$filedir."/".$trail.".html");
	if(!file_exists($filedir."/".$trail.".html"))
	{
		ShowMsg("创建播放器模板文件失败！","-1");
		exit();
	}
	$playername = gb2utf8($playername);
	$info = gb2utf8($info);
	$doc = new DOMDocument();
	$doc->formatOutput = true;
	$doc->load($playerKindsfile);
	$root = $doc->documentElement;
	$index = $doc->createElement('player');
	$root->appendChild($index);

	$open = $doc->createAttribute("open");
	$openvalue = $doc->createTextNode('1');
	$open->appendChild($openvalue);
	$index->appendChild($open);

	$sort = $doc->createAttribute("sort");
	$sortvalue = $doc->createTextNode($order);
	$sort->appendChild($sortvalue);
	$index->appendChild($sort);

	$postfix = $doc->createAttribute("postfix");
	$postfixvalue = $doc->createTextNode($trail);
	$postfix->appendChild($postfixvalue);
	$index->appendChild($postfix);

	$flag = $doc->createAttribute("flag");
	$flagvalue = $doc->createTextNode($playername);
	$flag->appendChild($flagvalue);
	$index->appendChild($flag);

	$des = $doc->createAttribute("des");
	$desvalue = $doc->createTextNode("");
	$des->appendChild($desvalue);
	$index->appendChild($des);

	$intro = $doc->createElement("intro");
	$introvalue = $doc->createCDATASection($info);
	$intro->appendChild($introvalue);
	$index->appendChild($intro);

	$doc->save($playerKindsfile);
	ShowMsg("操作成功！","admin_player.php?action=boardsource");
	exit();
}
elseif($action=="delete")
{
	$id = intval($id);
	$xml = simplexml_load_file($playerKindsfile);
	if(!$xml){$xml = simplexml_load_string(file_get_contents($playerKindsfile));}
	$i=0;
	$found=false;
	$postfix='';
	foreach($xml as $player){
		$i++;
		if($i==$id){
			$postfix=(string)$player['postfix'];
			unset($xml->player[$i-1]); //索引从0开始。
			$found=true;
			break;
			}
	}
	if(!$found)
	{
		ShowMsg("需要删除的播放来源不存在！","-1");
		exit();
	}
	$xml->asXML($playerKindsfile);
	//文件名取自被删条目的postfix属性，白名单校验后才删除，不再信任外部playerfile参数
	if(preg_match('/^[0-9a-zA-Z_\-]+$/',$postfix))
	{
		$filedir='../js/player/'.$postfix.'.html';
		if(file_exists($filedir)) unlink($filedir);
	}
	ShowMsg("操作成功！","admin_player.php?action=boardsource");
	exit();
}
else
{
	$fp = fopen($m_file,'r');
	$player = fread($fp,filesize($m_file));
	fclose($fp);
	$playerWidth=getrulevalue($player,"playerw='","';");
	$playerHeight=getrulevalue($player,"playerh='","';");
	$mplayerWidth=getrulevalue($player,"mplayerw='","';");
	$mplayerHeight=getrulevalue($player,"mplayerh='","';");
	$playerBeforeAdUrl=getrulevalue($player,"adsPage=\"","\";");
	$playerBeforeTime=getrulevalue($player,"adsTime=",";");
	
	$jxAname=getrulevalue($player,"jxAname=\"","\";");
	$jxBname=getrulevalue($player,"jxBname=\"","\";");
	$jxCname=getrulevalue($player,"jxCname=\"","\";");
	$jxDname=getrulevalue($player,"jxDname=\"","\";");
	$jxEname=getrulevalue($player,"jxEname=\"","\";");
	$jxFname=getrulevalue($player,"jxFname=\"","\";");
	$jxGname=getrulevalue($player,"jxGname=\"","\";");
	$jxHname=getrulevalue($player,"jxHname=\"","\";");
	$jxIname=getrulevalue($player,"jxIname=\"","\";");
	
	$jxAapi=getrulevalue($player,"jxAapi=\"","\";");
	$jxBapi=getrulevalue($player,"jxBapi=\"","\";");
	$jxCapi=getrulevalue($player,"jxCapi=\"","\";");
	$jxDapi=getrulevalue($player,"jxDapi=\"","\";");
	$jxEapi=getrulevalue($player,"jxEapi=\"","\";");
	$jxFapi=getrulevalue($player,"jxFapi=\"","\";");
	$jxGapi=getrulevalue($player,"jxGapi=\"","\";");
	$jxHapi=getrulevalue($player,"jxHapi=\"","\";");
	$jxIapi=getrulevalue($player,"jxIapi=\"","\";");
	
	$forcejx=getrulevalue($player,"forcejx=\"","\";");
	$unforcejx=getrulevalue($player,"unforcejx=\"","\";");
	
	include(sea_ADMIN.'/templets/admin_player.htm');
	exit();
}

function getrulevalue($content,$str1,$str2)
{
	if(!empty($content) && !empty($str1) && !empty($str2)){
		$labelRule = buildregx($str1."(.*?)".$str2,"is");
		preg_match_all($labelRule,$content,$ar);
		return $ar[1][0];
	}
}
?>