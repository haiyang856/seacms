<?php
error_reporting(0);
session_start();
require_once(__DIR__.'/include/gpc.filter.php');
  
require_once("include/common.php");
require_once(sea_INC."/splitword.class.php");
require_once(sea_INC."/ai.php");  // AI 搜索辅助
//前置跳转start
$cs=$_SERVER["REQUEST_URI"];
if($GLOBALS['cfg_mskin']==3 AND $GLOBALS['isMobile']==1){header("location:$cfg_mhost$cs");}
if($GLOBALS['cfg_mskin']==4 AND $GLOBALS['isMobile']==1){header("location:$cfg_mhost");}
//前置跳转end
require_once(sea_INC."/main.class.php");

if($cfg_search_type =='0')
{
	ShowMsg("搜索系统关闭！","index.php","0",$cfg_search_time*1000);
	exit();
}
if($cfg_search_type =='2')
{
	include_once("include/scheck.php");
	$cfg_search_type ='-1';
}

$schwhere = '';
foreach($_GET as $k=>$v)
{
	$tmpv = RemoveXSS($v);
	$$k=_RunMagicQuotes($tmpv);
	$schwhere.= "&$k=".urlencode($$k);
}
$schwhere = ltrim($schwhere,'&');
$page = (isset($page) && is_numeric($page)) ? max(1, intval($page)) : 1;
if(!isset($searchtype) || $searchtype != 5){
	$searchtype = intval($cfg_search_type);
} else {
	$searchtype = 5;
}
$tid = (isset($tid) && is_numeric($tid)) ? max(0, intval($tid)) : 0;
$uptypeid=GetTopid($tid);
if($uptypeid== 0){$uptypeid=$tid;}
if(!isset($searchword)) $searchword = '';
$action = $_REQUEST['action'] ?? '';
$searchword = RemoveXSS(stripslashes($searchword));
$searchword = addslashes(cn_substr($searchword,20));
$searchword = trim($searchword);

$jq = RemoveXSS(stripslashes($jq ?? ''));
$jq = addslashes(cn_substr($jq,10));

$area = RemoveXSS(stripslashes($area ?? ''));
$area = addslashes(cn_substr($area,10));

$year = RemoveXSS(stripslashes($year ?? ''));
// 严格 4 位数字年份, 空值保留
if($year !== '' && !preg_match('/^\d{4}$/', $year)) $year = '';
$year = addslashes(cn_substr($year,4));

$yuyan = RemoveXSS(stripslashes($yuyan ?? ''));
$yuyan = addslashes(cn_substr($yuyan,10));

$letter = RemoveXSS(stripslashes($letter ?? ''));
$letter = addslashes(cn_substr($letter,2));

$state = RemoveXSS(stripslashes($state ?? ''));
$state = addslashes(cn_substr($state,2));

$ver = RemoveXSS(stripslashes($ver ?? ''));
$ver = addslashes(cn_substr($ver,10));

$money = RemoveXSS(stripslashes($money ?? ''));
$money = addslashes(cn_substr($money,2));

$order = RemoveXSS(stripslashes($order ?? ''));
// 排序字段白名单, 不在列表中的值强制回退为空(默认排序)
$_allowed_orders = array('hit','hitasc','id','idasc','time','timeasc','commend','commendasc','score','scoreasc');
if(!in_array($order, $_allowed_orders, true)) $order = '';
$order = addslashes(cn_substr($order,16));

if($cfg_notallowsstr !='' && $searchtype!=5)
{
	$sstr=m_eregi_replace($cfg_notallowsstr,'s-e-a-c-m-s',$searchword);
	$sarray=explode('s-e-a-c-m-s', $sstr);
	$r = null;
	array_walk($sarray, function($v) use (&$r){$r[$v] = strlen($v);});
    $searchword = array_search(max($r), $r);
}
if($searchword==''&&$searchtype!=5)
{
	ShowMsg('关键字不能为空！','index.php','0',$cfg_search_time*1000);
	exit();
}

echoSearchPage();

function echoSearchPage()
{
	global $dsql,$cfg_iscache,$mainClassObj,$page,$t1,$cfg_search_time,$searchtype,$searchword,$tid,$uptypeid,$year,$letter,$area,$yuyan,$state,$ver,$order,$jq,$money,$cfg_basehost,$cfg_issearchlog;

	/* ================ AI 搜索关键词纠正 ================ */
	$GLOBALS['ai_search_tip_html'] = '';
	/* ================== AI 纠正结束 ================== */
	
	$orderarr=array('id','idasc','time','timeasc','hit','hitasc','commend','commendasc','score','scoreasc','dayhit','weekhit','monthhit','random','douban','mtime','imdb','dayhitasc','weekhitasc','monthhitasc','mtimeasc','imdbasc');
    if(!(in_array($order,$orderarr))){$order='time';}
	
	if(strpos($searchword,'{searchpage:')) {ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}

function check_str($str,$key){
 foreach($key as $v){
  if(strpos($str,$v)>-1){
   return true;
  }
 }
 return false;
}

$key = array('{','}','(',')','=',',',';','"','<','>','<script','<iframe','@','&','%','$','#','*',':','_','.','if(','/','\\');
$page=intval($page);
$tid=intval($tid);
if(check_str($searchword,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}
if(check_str($tid,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}
if(check_str($year,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}
if(check_str($letter,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}
if(check_str($area,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}
if(check_str($yuyan,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}
if(check_str($state,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}
if(check_str($ver,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}
if(check_str($order,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}
if(check_str($jq,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}
if(check_str($money,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}
if(check_str($page,$key)){ShowMsg('请勿输入危险字符！','index.php','0',$cfg_search_time*1000);exit;}

	if(intval($searchtype)==5)
	{
		$searchTemplatePath = "/templets/".$GLOBALS['cfg_df_style']."/".$GLOBALS['cfg_df_html']."/cascade.html";
		if($GLOBALS['cfg_mskin']!=0 AND $GLOBALS['cfg_mskin']!=3 AND $GLOBALS['cfg_mskin']!=4  AND $GLOBALS['isMobile']==1)
		{$searchTemplatePath = "/templets/".$GLOBALS['cfg_df_mstyle']."/".$GLOBALS['cfg_df_html']."/cascade.html";}
		$typeStr = !empty($tid)?intval($tid).'_':'0_';
		$yearStr = !empty($year)?PinYin($year).'_':'0_';
		$letterStr = !empty($letter)?$letter.'_':'0_';
		$areaStr = !empty($area)?PinYin($area).'_':'0_';
		$orderStr = !empty($order)?$order.'_':'0_';
		$jqStr = !empty($jq)?PinYin($jq).'_':'0_';
		$yuyanStr = !empty($yuyan)?PinYin($yuyan).'_':'0_';
		$stateStr=!empty($state)?$state.'_':'0_';
		$moneyStr=!empty($money)?$money.'_':'0_';
		$verStr=!empty($verStr)?PinYin($verStr).'_':'0_';
		$cacheName="parse_cascade_".$typeStr.$GLOBALS['cfg_mskin'].$GLOBALS['isMobile'];
		$pSize = getPageSizeOnCache($searchTemplatePath,"cascade","");
	}else
	{
		if($cfg_search_time&&$page==1) checkSearchTimes($cfg_search_time);
		$searchTemplatePath = "/templets/".$GLOBALS['cfg_df_style']."/".$GLOBALS['cfg_df_html']."/search.html";
		if($GLOBALS['cfg_mskin']!=0 AND $GLOBALS['cfg_mskin']!=3 AND $GLOBALS['cfg_mskin']!=4  AND $GLOBALS['isMobile']==1)
		{$searchTemplatePath = "/templets/".$GLOBALS['cfg_df_mstyle']."/".$GLOBALS['cfg_df_html']."/search.html";}
		$cacheName="parse_search_".$GLOBALS['cfg_mskin'].$GLOBALS['isMobile'];
		$pSize = getPageSizeOnCache($searchTemplatePath,"search","");
	}
	if (empty($pSize)) $pSize=12;
	switch (intval($searchtype)) {
		case -1:
			$whereStr=" where v_recycled=0 and (v_name like '%$searchword%' or v_actor like '%$searchword%' or v_director like '%$searchword%' or v_tags like '%$searchword%' or v_nickname like '%$searchword%')";
		break;
		case 1:
			$whereStr=" where v_recycled=0 and v_name like '%$searchword%'";
		break;
		case 5:
			$whereStr=" where v_recycled=0";
			if(!empty($tid)) $whereStr.=" and (tid in (".getTypeId($tid).") or FIND_IN_SET('".$tid."',v_extratype)<>0)";
			if($year=="more")
				{
				$publishyeartxt=sea_DATA."/admin/publishyear.txt";
						$publishyear = array();
						if(filesize($publishyeartxt)>0)
						{
							$publishyear = file($publishyeartxt);
						}
						$yearArray=$publishyear;
						$yeartxt= implode(',',$yearArray);
						$whereStr.=" and v_publishyear not in ($yeartxt)";
				}
			if(!empty($year) AND $year!="more")
				{$whereStr.=" and v_publishyear='$year'";}
			if($letter=="0-9")
				{$whereStr.=" and v_letter in ('0','1','2','3','4','5','6','7','8','9')";}
			if(!empty($letter) AND $letter!="0-9")
				{$whereStr.=" and v_letter='$letter'";}
			if(!empty($area)) $whereStr.=" and v_publisharea='$area'";
			if(!empty($yuyan)) $whereStr.=" and v_lang='$yuyan'";
			if(!empty($jq)) $whereStr.=" and v_jq like'%$jq%'";
			if($state=='l') $whereStr.=" and v_state !=0";
			if($state=='w') $whereStr.=" and v_state=0";
			if($money=='s') $whereStr.=" and v_money !=0";
			if($money=='m') $whereStr.=" and v_money=0";
			if(!empty($ver)) $whereStr.=" and v_ver='$ver'";
		break;
	}
	$sql="select count(*) as dd from sea_data ".$whereStr;
	$row = $dsql->GetOne($sql);
	if(is_array($row))
	{
		$TotalResult = $row['dd'];
	}
	else
	{
		$TotalResult = 0;
	}
	$pCount = ceil($TotalResult/$pSize);

	/* ================ AI 搜索关键词纠正（仅当搜索结果为空时触发） ================ */
	if($TotalResult == 0 && intval($searchtype) != 5 && trim($searchword) != '')
	{
		// 只响应同源页面发起的搜索，降低被外部页面或脚本批量触发的风险
		$aiSource = '';
		if(!empty($_SERVER['HTTP_ORIGIN'])) $aiSource = $_SERVER['HTTP_ORIGIN'];
		elseif(!empty($_SERVER['HTTP_REFERER'])) $aiSource = $_SERVER['HTTP_REFERER'];

		$aiHost = isset($_SERVER['HTTP_HOST']) ? strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'])) : '';
		$aiSrcHost = $aiSource !== '' ? strtolower((string)parse_url($aiSource, PHP_URL_HOST)) : '';

		if($aiHost !== '' && $aiSrcHost === $aiHost)
		{
			$aiOriginal = stripslashes($searchword);
			$aiCorrection = deepseek_correct_keyword($aiOriginal);
			if(!$aiCorrection['ok'] && !empty($aiCorrection['limited']))
			{
				$GLOBALS['ai_search_tip_html'] = '<div style="max-width:900px;margin:10px auto;padding:10px 16px;background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;color:#92400e;font-size:13px;line-height:1.6;">AI 服务暂不可用</div>';
			}
			elseif($aiCorrection['ok'] && $aiCorrection['changed'])
			{
				$corrected = addslashes(cn_substr($aiCorrection['corrected'], 20));
				if($corrected !== '' && !check_str($corrected, $key))
				{
					switch(intval($searchtype))
					{
						case -1:
							$newWhereStr = " where v_recycled=0 and (v_name like '%$corrected%' or v_actor like '%$corrected%' or v_director like '%$corrected%' or v_tags like '%$corrected%' or v_nickname like '%$corrected%')";
						break;
						case 1:
							$newWhereStr = " where v_recycled=0 and v_name like '%$corrected%'";
						break;
						default:
							$newWhereStr = '';
						break;
					}

					// 只有纠正后确实能搜到结果，才采用纠正词
					if($newWhereStr !== '')
					{
						$row2 = $dsql->GetOne("select count(*) as dd from sea_data ".$newWhereStr);
						$newTotal = is_array($row2) ? intval($row2['dd']) : 0;
						if($newTotal > 0)
						{
							$searchword = $corrected;
							$whereStr = $newWhereStr;
							$TotalResult = $newTotal;
							$pCount = ceil($TotalResult/$pSize);
							$GLOBALS['ai_search_tip_html'] = '<div style="max-width:900px;margin:10px auto;padding:10px 16px;background:#e0f2fe;border:1px solid #7dd3fc;border-radius:6px;color:#075985;font-size:13px;line-height:1.6;">✅ 您搜索的 <b>'.htmlspecialchars($aiOriginal).'</b> 可能输入有误，已自动纠正为 <b>'.htmlspecialchars(stripslashes($corrected)).'</b> 为您搜索。</div>';
						}
					}
				}
			}
		}
	}
	/* ================== AI 纠正结束 ================== */
	if($cfg_iscache){
		if(chkFileCache($cacheName)){
			$content = getFileCache($cacheName);
		}else{
			$content = parseSearchPart($searchTemplatePath);
			setFileCache($cacheName,$content);
		}
	}else{
			$content = parseSearchPart($searchTemplatePath);
	}
	$content = str_replace("{searchpage:page}",$page,$content);
	$content = str_replace("{seacms:searchword}",$searchword,$content);
	$content = str_replace("{seacms:searchnum}",$TotalResult,$content);
	$content = str_replace("{searchpage:ordername}",$order,$content);
	
	$content = str_replace("{searchpage:order-hit-link}",$cfg_basehost."/search.php?page=".$page."&searchtype=5&order=hit&tid=".$tid."&area=".$area."&year=".$year."&letter=".$letter."&yuyan=".$yuyan."&state=".$state."&money=".$money."&ver=".$ver."&jq=".$jq,$content);
	$content = str_replace("{searchpage:order-hitasc-link}",$cfg_basehost."/search.php?page=".$page."&searchtype=5&order=hitasc&tid=".$tid."&area=".$area."&year=".$year."&letter=".$letter."&yuyan=".$yuyan."&state=".$state."&money=".$money."&ver=".$ver."&jq=".$jq,$content);
	
	$content = str_replace("{searchpage:order-id-link}",$cfg_basehost."/search.php?page=".$page."&searchtype=5&order=id&tid=".$tid."&area=".$area."&year=".$year."&letter=".$letter."&yuyan=".$yuyan."&state=".$state."&money=".$money."&ver=".$ver."&jq=".$jq,$content);
	$content = str_replace("{searchpage:order-idasc-link}",$cfg_basehost."/search.php?page=".$page."&searchtype=5&order=idasc&tid=".$tid."&area=".$area."&year=".$year."&letter=".$letter."&yuyan=".$yuyan."&state=".$state."&money=".$money."&ver=".$ver."&jq=".$jq,$content);
	
	$content = str_replace("{searchpage:order-time-link}",$cfg_basehost."/search.php?page=".$page."&searchtype=5&order=time&tid=".$tid."&area=".$area."&year=".$year."&letter=".$letter."&yuyan=".$yuyan."&state=".$state."&money=".$money."&ver=".$ver."&jq=".$jq,$content);
	$content = str_replace("{searchpage:order-timeasc-link}",$cfg_basehost."/search.php?page=".$page."&searchtype=5&order=timeasc&tid=".$tid."&area=".$area."&year=".$year."&letter=".$letter."&yuyan=".$yuyan."&state=".$state."&money=".$money."&ver=".$ver."&jq=".$jq,$content);
	
	$content = str_replace("{searchpage:order-commend-link}",$cfg_basehost."/search.php?page=".$page."&searchtype=5&order=commend&tid=".$tid."&area=".$area."&year=".$year."&letter=".$letter."&yuyan=".$yuyan."&state=".$state."&money=".$money."&ver=".$ver."&jq=".$jq,$content);
	$content = str_replace("{searchpage:order-commendasc-link}",$cfg_basehost."/search.php?page=".$page."&searchtype=5&order=commendasc&tid=".$tid."&area=".$area."&year=".$year."&letter=".$letter."&yuyan=".$yuyan."&state=".$state."&money=".$money."&ver=".$ver."&jq=".$jq,$content);
	
	$content = str_replace("{searchpage:order-score-link}",$cfg_basehost."/search.php?page=".$page."&searchtype=5&order=score&tid=".$tid."&area=".$area."&year=".$year."&letter=".$letter."&yuyan=".$yuyan."&state=".$state."&money=".$money."&ver=".$ver."&jq=".$jq,$content);
	$content = str_replace("{searchpage:order-scoreasc-link}",$cfg_basehost."/search.php?page=".$page."&searchtype=5&order=scoreasc&tid=".$tid."&area=".$area."&year=".$year."&letter=".$letter."&yuyan=".$yuyan."&state=".$state."&money=".$money."&ver=".$ver."&jq=".$jq,$content);
	if(intval($searchtype)==5)
	{
		$tname = !empty($tid)?getTypeNameOnCache($tid):'全部';
		$jq = !empty($jq)?$jq:'全部';
		$area = !empty($area)?$area:'全部';
		$year = !empty($year)?$year:'全部';
		$yuyan = !empty($yuyan)?$yuyan:'全部';
		$letter = !empty($letter)?$letter:'全部';
		$state = !empty($state)?$state:'全部';
		$ver = !empty($ver)?$ver:'全部';
		$money = !empty($money)?$money:'全部';
		$content = str_replace("{searchpage:type}",$tid,$content);
		$content = str_replace("{searchpage:uptype}",$uptypeid,$content);
		$content = str_replace("{searchpage:typename}",$tname ,$content);
		$content = str_replace("{searchpage:year}",$year,$content);
		$content = str_replace("{searchpage:area}",$area,$content);
		$content = str_replace("{searchpage:letter}",$letter,$content);
		$content = str_replace("{searchpage:lang}",$yuyan,$content);
		$content = str_replace("{searchpage:jq}",$jq,$content);
		if($state=='w'){$state2="完结";}elseif($state=='l'){$state2="连载中";}else{$state2="全部";}
		if($money=='m'){$money2="免费";}elseif($money=='s'){$money2="收费";}else{$money2="全部";}
		$content = str_replace("{searchpage:state}",$state2,$content);
		$content = str_replace("{searchpage:money}",$money2,$content);
		$content = str_replace("{searchpage:ver}",$ver,$content);
		$content=$mainClassObj->parsePageList($content,"",$page,$pCount,$TotalResult,"cascade");
		$content=$mainClassObj->parseSearchItemList($content,"type",'');
		$content=$mainClassObj->parseSearchItemList($content,"year",'');
		$content=$mainClassObj->parseSearchItemList($content,"area",'');
		$content=$mainClassObj->parseSearchItemList($content,"letter",'');
		$content=$mainClassObj->parseSearchItemList($content,"lang",'');
		$jqupid=getUpId($tid);
		if($jqupid==0 OR $jqupid=='0'){$jqupid=$tid;}
		if($jqupid=='' OR empty($jqupid)){$jqupid='0';}
		$content=$mainClassObj->parseSearchItemList($content,"jq",$jqupid);
		$content=$mainClassObj->parseSearchItemList($content,"state",'');
		$content=$mainClassObj->parseSearchItemList($content,"ver",'');
		$content=$mainClassObj->parseSearchItemList($content,"money",'');
	}else
	{
		$content=$mainClassObj->parsePageList($content,"",$page,$pCount,$TotalResult,"search");
	}
	$content=replaceCurrentTypeId($content,-444);
	$content=$mainClassObj->parseIf($content);
	$content=str_replace("{seacms:member}",front_member(),$content);
	$searchPageStr = $content;
	if($cfg_issearchlog=="y"){GetKeywords($searchword,$TotalResult);}

	// AI 搜索纠正提示条（如果有），注入到页面最顶部
	$tipHtml = isset($GLOBALS['ai_search_tip_html']) ? $GLOBALS['ai_search_tip_html'] : '';
	if($tipHtml !== '')
	{
		$searchPageStr = $tipHtml . $searchPageStr;
	}

	echo str_replace("{seacms:runinfo}",getRunTime($t1),$searchPageStr) ;
}

function parseSearchPart($templatePath)
{
	global $mainClassObj,$tid;
	$currentTypeId = empty($tid)?0:$tid;
	$content=loadFile(sea_ROOT.$templatePath);
	$content=$mainClassObj->parseTopAndFoot($content);
	$content=$mainClassObj->parseAreaList($content);
	$content=$mainClassObj->parseHistory($content);
	$content=$mainClassObj->parseSelf($content);
	$content=$mainClassObj->parseGlobal($content);
	$content=$mainClassObj->parseMenuList($content,"",$currentTypeId);
	$content=$mainClassObj->parseVideoList($content,$currentTypeId,'','');
	$content=$mainClassObj->parsenewsList($content,$currentTypeId,'','');
	$content=$mainClassObj->parseTopicList($content);
	return $content;
}

function checkSearchTimes($searchtime)
{
	global $cfg_search_time;
	if(GetCookie("ssea2_search")=="ok")
	{
		ShowMsg('搜索限制为'.$searchtime.'秒一次','index.php','0',$cfg_search_time);
		//PutCookie("ssea2_search","ok",$searchtime);
		exit;
	}else{
		PutCookie("ssea2_search","ok",$searchtime);
	}
	
}

//获得关键字的分词结果，并保存到数据库
function GetKeywords($keyword,$TotalResult)
{
	global $dsql;
	$keyword = cn_substr($keyword,50);
	if($keyword=="" OR empty($keyword)){return $keywords;}
	$row = $dsql->GetOne("Select spwords From `sea_search_keywords` where keyword='".addslashes($keyword)."'; ");
	if(!is_array($row))
	{
		if(strlen($keyword)>7)
		{
			$sp = new SplitWord();
			$keywords = $sp->SplitRMM($keyword);
			$sp->Clear();
			$keywords = m_ereg_replace("[ ]{1,}"," ",trim($keywords));
		}
		else
		{
			$keywords = $keyword;
		}
		$inquery = "INSERT INTO `sea_search_keywords`(`keyword`,`spwords`,`count`,`result`,`lasttime`,`tid`)
  VALUES ('".addslashes($keyword)."', '".addslashes($keywords)."', '1', '$TotalResult', '".time()."','0'); ";
		$dsql->ExecuteNoneQuery($inquery);
	}
	else
	{
		$dsql->ExecuteNoneQuery("Update `sea_search_keywords` set count=count+1,tid='0',result='$TotalResult',lasttime='".time()."' where keyword='".addslashes($keyword)."'; ");
		$keywords = $row['spwords'];
	}
	return $keywords;
}
