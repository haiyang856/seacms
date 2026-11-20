<?php 
require_once(dirname(__FILE__)."/config.php");
require(sea_INC.'/image.func.php');
require_once("../include/common.php");
$mysqlver=$dsql->GetOne("select VERSION()");

// ========== 仪表盘数据采集 ==========
// 内容统计（与 admin_info.htm 原始查询一致）
$vodTotal = $dsql->GetOne("select count(*) as dd from sea_data");
$vodTotal=is_array($vodTotal)?$vodTotal:array();
$newsTotal = $dsql->GetOne("select count(*) as dd from sea_news");
$newsTotal=is_array($newsTotal)?$newsTotal:array();
$userTotal = $dsql->GetOne("select count(*) as dd from sea_member");
$userTotal=is_array($userTotal)?$userTotal:array();
$todayUser = $dsql->GetOne("select count(*) as dd from sea_member where TO_DAYS(from_unixtime(regtime)) = TO_DAYS(NOW())");
$todayUser=is_array($todayUser)?$todayUser:array();
$gbookWait = $dsql->GetOne("select count(*) as dd from sea_guestbook where ischeck=0");
$errWait = $dsql->GetOne("select count(*) as dd from sea_erradd where ischeck=0");
$vplWait = $dsql->GetOne("select count(*) as dd from sea_comment where ischeck=0 and m_type != 1");
$nplWait = $dsql->GetOne("select count(*) as dd from sea_comment where ischeck=0 and m_type = 1");
$gbookWait=is_array($gbookWait)?$gbookWait:array(); $errWait=is_array($errWait)?$errWait:array(); $vplWait=is_array($vplWait)?$vplWait:array(); $nplWait=is_array($nplWait)?$nplWait:array(); $totalWait = $gbookWait['dd'] + $errWait['dd'] + $vplWait['dd'] + $nplWait['dd'];

// 远程图片数（与 admin_info.htm 一致）
require_once(sea_DATA."/config.ftp.php");
$remotePic = $dsql->GetOne("select count(*) as dd from sea_data where instr(v_pic,'#err')=0".($app_ftp=='0'?"":" and instr(v_pic,'$app_ftpurl')=0")." and instr(v_pic,'http')!=0");
$remotePicNews = $dsql->GetOne("select count(*) as dd from sea_news where instr(n_pic,'#err')=0".($app_ftp=='0'?"":" and instr(n_pic,'$app_ftpurl')=0")." and instr(n_pic,'http')!=0");
$remotePic=is_array($remotePic)?$remotePic:array(); $remotePicNews=is_array($remotePicNews)?$remotePicNews:array(); $remotePicTotal = $remotePic['dd'] + $remotePicNews['dd'];

// 分类数量
$vodTypeTotal = $dsql->GetOne("select count(*) as dd from sea_type where tptype=0");
$newsTypeTotal = $dsql->GetOne("select count(*) as dd from sea_type where tptype=1");
$vodTypeTotal=is_array($vodTypeTotal)?$vodTypeTotal:array(); $newsTypeTotal=is_array($newsTypeTotal)?$newsTypeTotal:array(); $allTypeTotal = $vodTypeTotal['dd'] + $newsTypeTotal['dd'];

// 今日新增
$todayVod = $dsql->GetOne("select count(*) as dd from sea_data where TO_DAYS(from_unixtime(v_addtime)) = TO_DAYS(NOW()) AND v_recycled != 1");
$todayVod=is_array($todayVod)?$todayVod:array();
$todayNews = $dsql->GetOne("select count(*) as dd from sea_news where TO_DAYS(from_unixtime(n_addtime)) = TO_DAYS(NOW()) AND n_recycled != 1");
$todayNews=is_array($todayNews)?$todayNews:array();

// Gzip 压缩是否实际开启（zlib.output_compression 或已注册 ob_gzhandler）
$gzipOn = false;
if (@ini_get('zlib.output_compression') && strtolower(@ini_get('zlib.output_compression')) !== 'off') {
	$gzipOn = true;
} else {
	foreach (ob_list_handlers() as $h) {
		if (stripos($h, 'ob_gzhandler') !== false) { $gzipOn = true; break; }
	}
}
$gzipEnabled = $gzipOn ? 'ON' : 'OFF';

// 安全检测
$safeMode = @ini_get("safe_mode") ? 'ON' : 'OFF';
// OPcache 字节码缓存状态（register_globals 自 PHP 5.4 移除，改为检测 PHP 8 的 OPcache）
$opcacheStatus = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
$opcacheEnabled = is_array($opcacheStatus) ? 'ON' : 'OFF';
// JIT 即时编译状态（magic_quotes_gpc 自 PHP 5.4 移除，改为检测 PHP 8 的 JIT，依赖 OPcache）
$jitEnabled = (is_array($opcacheStatus) && !empty($opcacheStatus['jit']['enabled'])) ? 'ON' : 'OFF';
$allowUrlFopen = filter_var(@ini_get("allow_url_fopen"), FILTER_VALIDATE_BOOLEAN) ? 'ON' : 'OFF';
$maxExecTime = @ini_get("max_execution_time");
if($maxExecTime === false || $maxExecTime === '' || $maxExecTime === null){
	$maxExecTime = 30;
}
$memoryLimit = @get_cfg_var("memory_limit") ?: '无';
$uploadMax = @ini_get("post_max_size");
$phpVer = @phpversion();
$gdVer = @gdversion();
$serverSoft = @$_SERVER['SERVER_SOFTWARE'];
$serverOS = PHP_OS;
$hostIP = @gethostbyname($_SERVER['SERVER_NAME']);
$serverPort = $_SERVER['SERVER_PORT'];
$mysqlVer = $mysqlver['0'];

// 函数检测（本项目实际使用的25个常被限制的关键PHP函数）
$needFuncs = array(
	// 命令执行类（高危，主机商常禁）
	'exec','shell_exec','proc_open','system','popen','passthru',
	// 网络连接类（采集/邮件依赖）
	'fsockopen','pfsockopen','stream_socket_client','dir',
	// 文件系统类（上传/备份依赖）
	'scandir','symlink','ini_set','putenv','chmod',
	// 编码处理类（模板/缓存依赖）
	'base64_decode','unserialize','openssl_encrypt',
	// 信息泄露类
	'phpinfo','mail',
	// 图像处理类（缩略图/水印依赖）
	'imagettftext','imagecreatetruecolor','imagejpeg',
	// 字符编码类（中文内容依赖）
	'iconv','mb_strlen'
);
$funcStatus = array();
foreach($needFuncs as $k){
	$funcStatus[$k] = function_exists($k);
}
$funcOkCount = count(array_filter($funcStatus));
$funcAllCount = count($funcStatus);
$funcOkAll = ($funcOkCount == $funcAllCount);

// 待审比例条
$maxWait = max($gbookWait['dd'], $errWait['dd'], $vplWait['dd'], $nplWait['dd'], 1);
	//require(sea_INC.'/inc/inc_fun_funAdmin.php');
	//$verLockFile = sea_ROOT.'/data/admin/ver.txt';
	//$fp = fopen($verLockFile,'r');
	//$upTime = trim(fread($fp,64));
	//fclose($fp);
	//$oktime = substr($upTime,0,4).'-'.substr($upTime,4,2).'-'.substr($upTime,6,2);
	//$oktimear = array(0,0,0,0,0);
	//$oktimear = explode('.',$upTime);
	//$oktime = $oktimear[2].'-'.$oktimear[3].'-'.$oktimear[4];
	//$offUrl = SpGetNewInfo();
function CmsgetDirSize($dir)
{ 
	$handle = opendir($dir);
	$sizeResult = '';
	while (false!==($FolderOrFile = readdir($handle)))
	{ 
		if($FolderOrFile != "." && $FolderOrFile != "..") 
		{ 
			if(is_dir("$dir/$FolderOrFile"))
			{ 
				$sizeResult += getDirSize("$dir/$FolderOrFile"); 
			}
			else
			{ 
				$sizeResult += filesize("$dir/$FolderOrFile"); 
			}
		}    
	}
	closedir($handle);
	return $sizeResult;
}
	// 单位自动转换函数
	$ver=$CmsScrpits.$CmsDomain.$CmsVer.$CmsFileType.$CmsBihe.$CmsScrpite;
	$info=$CmsScrpits.$CmsDomain.$CmsInfo.$CmsFileType.$CmsBihe.$CmsScrpite;
function CmsgetRealSize($size)
{ 
	$kb = 1024;         // Kilobyte
	$mb = 1024 * $kb;   // Megabyte
	$gb = 1024 * $mb;   // Gigabyte
	$tb = 1024 * $gb;   // Terabyte
	if($size == 0){
		return "0 B";
	}
	else if($size < $mb)
	{ 
     	return round($size/$kb,2)." K";
	}
	else if($size < $gb)
	{ 
    	return round($size/$mb,2)." M";
	}
	else if($size < $tb)
	{ 
    	return round($size/$gb,2)." G";
	}
	else
	{ 
     	return round($size/$tb,2)." T";
	}
}
function CmsgetTemplateType($filename){
	switch(strtolower($filename)){
		case 'index.html':
			$getTemplateType="首页模版";
			break;
		case "head.html":
			$getTemplateType="模板头文件";
			break;
		case "cascade.html":
			$getTemplateType="筛选页文件";
			break;	
		case "foot.html":
			$getTemplateType="模板尾文件";
			break;
		case "play.html":
			$getTemplateType="播放页模板";
			break;
		case "map.html":
			$getTemplateType="HTML地图页模板";
			break;
		case "search.html":
			$getTemplateType="搜索页模板";
			break;
		case "topic.html":
			$getTemplateType="专题页模板";
			break;
		case "topicindex.html":
			$getTemplateType="专题首页模板";
			break;
		case "comment.html":
			$getTemplateType="评论页模板";
			break;
		case "channel.html":
			$getTemplateType="分类页模板";
			break;
		case "openplay.html":
			$getTemplateType="播放页模板(弹窗模式)";
			break;
		case "content.html":
			$getTemplateType="内容页模板";
			break;
		case "gbook.html":
			$getTemplateType="留言本页面模板";
			break;
		case "login.html":
            $getTemplateType="登陆页面模板";
            break;
        case "news.html":
            $getTemplateType="文章内容页面模板";
            break;
        case "newsindex.html":
            $getTemplateType="文章首页面模板";
            break;
        case "newspage.html":
            $getTemplateType="文章列表页面模板";
            break;
		case "newssearch.html":
			$getTemplateType="文章搜索页面模板";
			break;
		case "reg.html":
			$getTemplateType="会员注册页面模板";
			break;
		case "newsmap.html":
			$getTemplateType="文章地图页面模板";
			break;
		case "newsjs.html":
			$getTemplateType="文章js调用模板";
			break;		
		default:
			if(stristr($filename,'.gif') or stristr($filename,'.jpg') or stristr($filename,'.png')){
				$getTemplateType="图片文件";
				}
			elseif(stristr($filename,'.css')){
				 $getTemplateType="样式文件";
				}
			elseif(stristr($filename,'self')){
				 $getTemplateType="自定义模板";
				}
			elseif(stristr($filename,'http.txt')){
				 $getTemplateType="伪静态配置模板";
				}
			elseif(stristr($filename,'.html') or stristr($filename,'.htm')){
				 $getTemplateType="静态页面文件";
				}
			elseif(stristr($filename,'.js')){
				$getTemplateType="脚本文件";
				}	
			else{
				$getTemplateType="其它文件";
				}
	}
	return $getTemplateType;
}
	include(sea_ADMIN.'/templets/index_body.htm');
// ========== 以下函数仅用于定义，模板已自包含 ==========
function CMSdownSinglePic($picUrl,$vid,$vname,$filePath,$infotype)
{
	$spanstr=empty($infotype) ? "" : "<br/>";
	if(empty($picUrl) || substr($picUrl,0,7)!='http://'){
		echo "数据<font color=red>".$vname."</font>的图片路径错误1,请检查图片地址是否有效  ".$spanstr;
		return false;
	}
	$fileext=getFileFormat($filePath);
	$ps=preg_split("/",$picUrl);
	$filename=urldecode($ps[count($ps)-1]);
	if ($fileext!="" && strpos("|.jpg|.gif|.png|.bmp|.jpeg|",strtolower($fileext))>0){
		if(!(strpos($picUrl,".ykimg.com/")>0)){
			if(empty($filename) || strpos($filename,".")==0){
				echo "数据<font color=red>".$vname."</font>的图片路径错误2,请检查图片地址是否有效 ".$spanstr;
				return false;
			}
		}
		$imgStream=getRemoteContent(substr($picUrl,0,strrpos($picUrl,'/')+1).str_replace('+','%20',urlencode($filename)));
		$createStreamFileFlag=createStreamFile($imgStream,$filePath);
		if($createStreamFileFlag){
			$streamLen=strlen($imgStream);
			if($streamLen<2048){
				echo "数据<font color=red>".$vname."</font>的图片下载发生错误5,请检查图片地址是否有效  ".$spanstr;
				return false;
			}else{
				return number_format($streamLen/1024,2);
			}
		}else{
			if(empty($vid)){
				echo "数据<font color=red>".$vname."</font>的图片下载发生错误3,请检查图片地址是否有效  ".$spanstr;
				return false;
			}else{
				echo "数据<font color=red>".$vname."</font>的图片下载发生错误4,id为<font color=red>".$vid."</font>,请检查图片地址是否有效  ".$spanstr;
				return false;
			}
		}
	}else{
		echo "数据<font color=red>".$vname."</font>的图片下载发生错误6,请检查图片地址是否有效  ".$spanstr;
		return false;
	}
}

function Cmsuploadftp($picpath,$picfile,$v_name,$picUrl)
{
	require_once(sea_INC."/ftp.class.php");
	$Newpicpath = str_replace("../","",$picpath);
	$ftp = new AppFtp($GLOBALS['app_ftphost'] ,$GLOBALS['app_ftpuser'] ,$GLOBALS['app_ftppass'] , $GLOBALS['app_ftpport'] , $GLOBALS['app_ftpdir']);
	if( $ftp->ftpStatus == 1){;
		$localfile= sea_ROOT .'/'. $Newpicpath . $picfile;
		$remotefile= $GLOBALS['app_ftpdir'].$Newpicpath . $picfile;
		$ftp -> mkdirs( $GLOBALS['app_ftpdir'].$Newpicpath );
		$ftpput = $ftp->put($localfile, $remotefile);
		if(!$ftpput){
			echo "数据$v_name上传图片到FTP远程服务器失败!本地地址$picUrl<br>";
			return false;
		}
		$ftp->bye();
		if ($GLOBALS['app_ftpdel']==1){
			unlink( $picpath . $picfile );
		}
	}
	else{
		echo $ftp->ftpStatusDes;return false;
	}
}
// 模板已自包含，以下为函数定义保留
function CmsmakeTypeOptionSelected_Multiple($topId, $separateStr, $span, $compareValue, $tptype=0)
{
	$tlist=getTypeListsOnCache($tptype);
	if ($topId!=0){$span.=$separateStr;}else{$span="";}
	if($compareValue==""){
$ids_arr="";}else{
$ids_arr = preg_split('[,]',$compareValue);} 
	foreach($tlist as $row)
	{
		
		if($row->upid==$topId)
		{
			
			for($i=0;$i<count($ids_arr);$i++)
			{
				if ($row->tid==$ids_arr[$i]){
					$selectedStr=" checked=checked";
					break;
					}
					else
					{
					$selectedStr="";
					}
			}
			
			echo "<input name=v_type_extra[] type=checkbox value=".$row->tid." ".$selectedStr.">".$row->tname."&nbsp;&nbsp;";
			makeTypeOptionSelected_Multiple($row->tid,$separateStr,$span,$compareValue,$tptype);
			
		}
	}
	if (!empty($span)){$span=substr($span,(strlen($span)-strlen($separateStr)));}
	
}


function CmsmakeTypeOptionSelected_Jq($topId, $separateStr, $span, $compareValue, $tptype=0)
{
	$tlist=getjqTypeListsOnCache($tptype);
	if ($topId!=0){$span.=$separateStr;}else{$span="";}
	if($compareValue==""){
$ids_arr="";}else{
$ids_arr = preg_split('[,]',$compareValue);}  
	foreach($tlist as $row)
	{
		
		if($row->upid==$topId)
		{
			
			for($i=0;$i<count($ids_arr);$i++)
			{
				if ($row->tname==$ids_arr[$i]){
					$selectedStr=" checked=checked";
					break;
					}
					else
					{
					$selectedStr="";
					}
			}
			
			echo "<input name=v_jqtype_extra[] type=checkbox value=".$row->tname." ".$selectedStr.">".$row->tname."&nbsp;&nbsp;";
			makeTypeOptionSelected_Jq($row->tid,$separateStr,$span,$compareValue,$tptype);
			
		}
	}
	if (!empty($span)){$span=substr($span,(strlen($span)-strlen($separateStr)));}
	
}
function CmsmakeTypeOptionSelected_Jq2($topId, $separateStr, $span, $compareValue, $tptype=0)
{
	$tlist=getjqTypeListsOnCache($tptype);
	if ($topId!=0){$span.=$separateStr;}else{$span="";}
	if($compareValue==""){
$ids_arr="";}else{
$ids_arr = preg_split('[,]',$compareValue);}  
	foreach($tlist as $row)
	{
		
		
			
			for($i=0;$i<count($ids_arr);$i++)
			{
				if ($row->tname==$ids_arr[$i]){
					$selectedStr=" checked=checked";
					break;
					}
					else
					{
					$selectedStr="";
					}
			}
			
			echo "<input name=v_jqtype_extra[] type=checkbox value=".$row->tname." ".$selectedStr.">".$row->tname."&nbsp;&nbsp;";
			
			
		
	}
	if (!empty($span)){$span=substr($span,(strlen($span)-strlen($separateStr)));}
	
}

function Cmsgetreferer()
{
	if(isset($_SERVER['HTTP_REFERER']))
	$refurl=$_SERVER['HTTP_REFERER'];
	$url='';
	if(!empty($refurl)){
		$refurlar=explode('/',$refurl);
		$i=count($refurlar)-1;
		$url=$refurlar[$i];
	}
	return $url;
}
?>

