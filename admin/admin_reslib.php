<?php 
@set_time_limit(0);
// PHP 8: libxml_disable_entity_loader 已废弃,外部实体默认禁用
ob_implicit_flush();
require_once(dirname(__FILE__)."/config.php");
require_once(sea_DATA."/mark/inc_photowatermark_config.php");
CheckPurview();

function xmlSafeStr($str)
    {   
    $str = preg_replace("/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f]/", '', $str); 
	$str = preg_replace('/on\w+=".*?"/i', '', $str);
    $str = preg_replace('/<script.*?>.*?<\/script.*?>/is', '', $str);
	$str = preg_replace('/<script.*?\>/is', '', $str);
	$str = preg_replace('/<script.*?\.js"/is', '', $str);
	$str = preg_replace('/<script.*?\.js/is', '', $str);
	$str = preg_replace('/<iframe.*?>.*?<\/iframe.*?>/is', '', $str);
	$str = preg_replace('/<iframe.*?\>/is', '', $str);
	$str = preg_replace('/<iframe.*?\.js"/is', '', $str);
	$str = preg_replace('/<iframe.*?\.js/is', '', $str);	
	return $str;
}


if(empty($action))
{
	$action = '';
}
$rid = isset($rid) ? $rid : '';
$url = isset($url) ? $url : '';
if(!empty($ac))
{
	$action = $ac;	
}
if(!empty($rid))
{
	$ressite = $rid;	
}
$rid=strip_tags($rid);
$isref=1;//是否使用sock采集 0为不是用,1为使用
$gatherWaitTime=$cfg_cjtime; //资源库采集每页数据间隔时间
$reslibMainSite="";
$backurl=isset($backurl)?$backurl:"admin_reslib.php";
$var_url=$url;

if($action=='')
{
	top();
	if(RWCache('collect_xml'))
	echo "<script>openCollectWin(400,'auto','上次采集未完成，继续采集？','".RWCache('collect_xml')."')</script>";
	echo '<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td><span id="xmllist"><h1><br><br>执行完毕:';
	echo date("Y-m-d H:i:s");
	echo '<br><br><a href="api.php">点击返回</a></h1></span></td>
  </tr>
</table>';
	bottom();
	exit();	
}



if($action=="list")
{
	@session_write_close();
	top();
	require_once(sea_INC.'/ai.php');
	$_aiCfg = deepseek_load_config();
	$aiBindEnabled = ($_aiCfg['open'] === 'on' && !empty($_aiCfg['api_key']));
	if (empty($pg)||$pg<=0)$pg=1;
	if($rid=='seazmt3zz2cmszmt')
	{
		$weburl=$var_url."?s=plus-api-xml-cms-max-list-true-cid-{$t}-h-{$h}-p-{$pg}-wd-".gbutf8($wd);
	}
	else
	{
		$weburl=$var_url.(strpos($var_url,'?')!==false?"&":"?")."h=".$h."&wd=".gbutf8($wd)."&t=".$t."&ac=list&bindtype=".getBindedLibIds()."&pg=".$pg;
	}
	
	// 自动检测数据格式
	$content = cget($weburl,$isref);
	$is_json = false;
	$json_data = null;
	$xml = null;
	
	// 尝试解析为 JSON
	$json_data = json_decode($content, true);
	if (json_last_error() === JSON_ERROR_NONE && isset($json_data['code'])) {
		$is_json = true;
	} else {
		// JSON 解析失败，尝试 XML
		$xml = simplexml_load_string(xmlSafeStr(str_replace("m_id","e_id[]",str_replace("?action=","?ressite=".$ressite."&action=",$content))),null,LIBXML_NOCDATA|LIBXML_NOENT);
		if(!$xml){
			$content = cget($weburl,0);
			$xml = simplexml_load_string(xmlSafeStr(str_replace("m_id","e_id[]",str_replace("?action=","?ressite=".$ressite."&action=",$content))),null,LIBXML_NOCDATA|LIBXML_NOENT);
		}
		if(!$xml){ 
			ShowMsg('获取资源失败', 'admin_reslib.php');
			exit;
		}
	}
	
	// 获取分页信息
	$totalcount = 0;
	$pagesize = 0;
	$currentpage = 0;
	$pagecount = 0;
	
	if ($is_json) {
		$totalcount = isset($json_data['total']) ? $json_data['total'] : 0;
		$pagesize = isset($json_data['limit']) ? $json_data['limit'] : 20;
		$currentpage = isset($json_data['page']) ? $json_data['page'] : 1;
		$pagecount = isset($json_data['pagecount']) ? $json_data['pagecount'] : 1;
	} else {
		$totalcount = $xml->list['recordcount'];
		$pagesize = $xml->list['pagesize'];
		$currentpage = $xml->list['page'];
		$pagecount = $xml->list['pagecount'];
	}
	?>
    
    
    
    
  <div class="container" id="cpcontainer">
  <table class="tb">
    <thead>
      <tr class="thead">
        <th colspan="4">&nbsp;<a href="?">采集平台</a></th>
      </tr>
      <tr>
        <th colspan="4"> <ul class="ul">
          <li><a href="?ac=list&amp;rid=<?php  echo $rid;?>&amp;url=<?php  echo $url;?>">全部</a></li>
          
          <?php 
          	
			// 根据数据格式显示分类
			if ($is_json && isset($json_data['class']) && is_array($json_data['class'])) {
				foreach($json_data['class'] as $ty)
				{
					$type_id = isset($ty['type_id']) ? $ty['type_id'] : 0;
					$type_name = isset($ty['type_name']) ? $ty['type_name'] : '';
					$isbangding = "";
					if(getBindedLocalId($rid."_".$type_id))
					{
						if(intval(getBindedLocalId($rid."_".$type_id)))
						{
							$isbangding = "已绑定";
						}
						else
						{
							$isbangding = "<font color='red'>未绑定</font>";
						}
					}
					else
					{
						$isbangding = "<font color='red'>未绑定</font>";
					}
					?>
           <li><a href="?ac=list&amp;url=<?php  echo $var_url?>&amp;rid=<?php  echo $rid ?>&amp;t=<?php  echo $type_id?>" ><?php  echo $type_name; ?></a>&nbsp;&nbsp;<label id="bind_<?php  echo $rid ?>_<?php  echo $type_id ?>"><b><a href="#" onClick="setBindType('<?php  echo $rid ?>_<?php  echo $type_id ?>',0)"><?php  echo $isbangding ?></a></b></label></li>
               
               <?php 
				}
			} else {
				foreach($xml->class->ty as $ty)
				{
					$isbangding = "";
					if(getBindedLocalId($rid."_".$ty['id']))
					{
						if(intval(getBindedLocalId($rid."_".$ty['id'])))
						{
							$isbangding = "已绑定";
						}
						else
						{
							$isbangding = "<font color='red'>未绑定</font>";
						}
					}
					else
					{
						$isbangding = "<font color='red'>未绑定</font>";
					}
					?>
           <li><a href="?ac=list&amp;url=<?php  echo $var_url?>&amp;rid=<?php  echo $rid ?>&amp;t=<?php  echo $ty['id']?>" ><?php  echo $ty; ?></a>&nbsp;&nbsp;<label id="bind_<?php  echo $rid ?>_<?php  echo $ty['id'] ?>"><b><a href="#" onClick="setBindType('<?php  echo $rid ?>_<?php  echo $ty['id'] ?>',0)"><?php  echo $isbangding ?></a></b></label></li>
               
               <?php 
				}
			}
		  
		  ?>
          <?php if($aiBindEnabled){ ?>
          <li style="float:none;clear:both;width:auto;margin:8px 3px 3px;display:inline-block;">
            <input type="button" class="btn" value="AI 一键绑定" style="height:24px;padding:0 12px;background:#33a47f;border:1px solid #fff;color:#fff;border-radius:3px;cursor:pointer;" onclick="aiBindAll(this)" />
            <span id="aibind_tip" style="color:#666;font-size:12px;margin-left:8px;"></span>
          </li>
          <?php } ?>
        </ul>
        <?php if($aiBindEnabled){ ?>
        <script type="text/javascript">
        var aiBindRid = <?php echo json_encode($rid); ?>;
        var aiBindUrl = <?php echo json_encode($var_url); ?>;
        function aiBindAll(btn){
          if(!confirm('将调用 AI 根据分类名称自动绑定全部未绑定的远端分类，是否继续？')) return;
          if(btn){ btn.disabled = true; btn.value = 'AI 匹配中...'; }
          var tip = document.getElementById('aibind_tip');
          if(tip){ tip.innerHTML = '<font color="#e67e22">正在请求 AI 匹配分类，请稍候...</font>'; }
          var xhr = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject('Microsoft.XMLHTTP');
          xhr.open('POST', 'admin_reslib.php?ac=aibind', true);
          xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
          xhr.onreadystatechange = function(){
            if(xhr.readyState != 4) return;
            if(btn){ btn.disabled = false; btn.value = 'AI 一键绑定'; }
            var res = null;
            try{ res = JSON.parse(xhr.responseText); }catch(e){}
            if(!res){ if(tip) tip.innerHTML = ''; alert('AI 绑定失败：' + xhr.responseText); return; }
            if(!res.ok){ if(tip) tip.innerHTML = ''; alert('AI 绑定失败：' + (res.error || '未知错误')); return; }
            if(tip){ tip.innerHTML = '<font color="green">已绑定 ' + res.bound + ' 个，未匹配 ' + res.unmatched + ' 个</font>'; }
            alert('AI 一键绑定完成\n\n远端分类共 ' + res.total + ' 个\n已绑定 ' + res.bound + ' 个\n未匹配 ' + res.unmatched + ' 个\n已存在绑定跳过 ' + res.skipped + ' 个');
            if(res.bound > 0){ location.reload(); }
          };
          xhr.send('rid=' + encodeURIComponent(aiBindRid) + '&url=' + encodeURIComponent(aiBindUrl));
        }
        </script>
        <?php } ?>
        </th>
      </tr>
      <tr>
        <th colspan="4"> <div class="cuspages" style="margin:0">
          <div class="pages" style="margin:0">
          共 <?php  echo($totalcount)?> 条数据 每页 <?php  echo $pagesize; ?> 条 当前 <?php  echo $currentpage?>/<?php  echo $pagecount?> 
          页码
          <a href="?ac=list&amp;rid=<?php  echo $rid;?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;pg=1&amp;url=<?php  echo $var_url?>">首页</a>
          <a href="?ac=list&amp;rid=<?php  echo $rid;?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;pg=<?php  echo ($pg-1) ?>&amp;url=<?php  echo $var_url?>">上一页</a>
          <?php  
		  	for($i=$pg-3;$i<($pg+4);$i++)
			{
				if($i<1){}
				elseif($i>$pagecount){}
				else{
			
		  ?>
          <a href='?ac=list&amp;rid=<?php  echo $rid;?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;pg=<?php  echo ($i) ?>&amp;url=<?php  echo $var_url?>'><?php  echo $i;?></a>
          <?php 
			}
			}
		  ?>
          
          <a href="?ac=list&amp;rid=<?php  echo $rid;?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;pg=<?php  echo ($pg+1) ?>&amp;url=<?php  echo $var_url?>">下一页</a>
          <a href="?ac=list&amp;rid=<?php  echo $rid;?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;pg=<?php  echo ($pagecount) ?>&amp;url=<?php  echo $var_url?>">尾页</a>&nbsp;&nbsp;
          跳转
            <input type="text" id="skip" value="" onKeyUp="this.value=this.value.replace(/[^\d]+/,'')" style="width:26px;height:14px;"/>
            <input type="button" value="确定" class="btn" onClick="location.href='?ac=list&amp;rid=<?php  echo $rid;?>&amp;url=<?php  echo $var_url?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;pg='+$('skip').value;"/>
          </div>
        </div>
        </th>
      </tr>
    </thead>
    <form action="?" method="get">
      <input type="hidden" name="ac" value="<?php  echo $action?>" />
      <input type="hidden" name="rid" value="<?php  echo $rid?>" />
      <input type="hidden" name="t" value="<?php  echo $t;?>" />
      <input type="hidden" name="h" value="<?php  echo $h;?>" />
      <input type="hidden" name="url" value="<?php  echo $var_url?>" />
      <tr>
        <th colspan="4"> &nbsp;
          <input type="button" class="btn" style="width:65px" onClick="checkOthers('input','ids[]')" value="全选反选" />
          &nbsp;
          <input type="button" class="btn" style="width:45px" onClick="$('choose').submit();" value="采集"/>
          &nbsp;
          <a onClick="ajaxFunction('?ac=day&amp;url=<?php  echo $var_url?>&rid=<?php  echo $rid;?>&t=<?php  echo $t;?>&h=24&backurl='+encodeURIComponent('admin_reslib.php?ac=list&amp;url=<?php  echo $var_url?>&rid=<?php  echo $rid;?>'))" href="#" >
          <input type="button" class="btn" style="width:65px" value="采集当天"/></a>
          <?php  if($t>=1){echo '&nbsp;';}?>
          <a onClick="ajaxFunction('?ac=type&amp;url=<?php  echo $var_url?>&rid=<?php  echo $rid;?>&t=<?php  echo $t;?>&backurl='+encodeURIComponent('admin_reslib.php?ac=list&amp;url=<?php  echo $var_url?>&rid=<?php  echo $rid;?>'))" href="#" ><input type="button" class="btn" style="width:85px;<?php  if($t<1){ ?>display:none;<?php  }else{?><?php  }?>" value="采集当前分类"/></a>
          &nbsp;
          <input type="button" class="btn" style="width:45px" onClick="location.reload()" value="刷新"/>
          &nbsp;&nbsp;查询：
          <input type="text" name="wd" value="" style="width:70px" />
          &nbsp;
          <input type="submit" class="btn" name="submit" value="搜 索" />
          &nbsp;
          <select name="select" onChange="location.href='?ac=list&amp;url=<?php  echo $var_url?>&rid=<?php  echo $rid;?>&t='+this.options[this.selectedIndex].value+'&h=&pg=&wd='">
           <option value="">按分类查看</option>
           <?php 
			// 根据数据格式显示分类选项
			if ($is_json && isset($json_data['class']) && is_array($json_data['class'])) {
				foreach($json_data['class'] as $ty)
				{
					$type_id = isset($ty['type_id']) ? $ty['type_id'] : 0;
					$type_name = isset($ty['type_name']) ? $ty['type_name'] : '';
          			?>
                     <option value="<?php  echo $type_id?>"><?php  echo $type_name;?></option>
               
               <?php 
				}
			} else {
				foreach($xml->class->ty as $ty)
				{

          			?>
                     <option value="<?php  echo $ty['id']?>"><?php  echo $ty;?></option>
               
               <?php 
				}
			}
			
		  ?>
            
           
          </select>
        <div id='wait'></div>
        </th>
      </tr>
    </form>
    <tr>
      <th>名称</th>
      <th>分类</th>
      <th>来源</th>
      <th width="135">时间</th>
    </tr>

    <tbody>
    </tbody>
<form action="?" method="post" name="choose" id="choose">
<input type="hidden" name="ac" value="select">
<input type="hidden" name="rid" value="<?php  echo($rid)?>">
<input type="hidden" name="backurl" value="?ac=list&rid=<?php  echo($rid)?>&t=<?php  echo $t;?>&h=<?php  echo $h;?>&wd=<?php  echo $wd;?>&pg=<?php  echo $pg;?>&amp;url=<?php  echo $var_url?>">
<input type="hidden" name="url" value="<?php  echo $var_url?>" />
 
    <?php 
	
	// 根据数据格式显示视频列表
	if ($is_json && isset($json_data['list']) && is_array($json_data['list'])) {
		foreach($json_data['list'] as $video)
		{
			$vod_id = isset($video['vod_id']) ? $video['vod_id'] : 0;
			$vod_name = isset($video['vod_name']) ? $video['vod_name'] : '';
			$vod_remarks = isset($video['vod_remarks']) ? $video['vod_remarks'] : '';
			$type_id = isset($video['type_id']) ? $video['type_id'] : 0;
			$type_name = isset($video['type_name']) ? $video['type_name'] : '';
			$vod_time = isset($video['vod_time']) ? $video['vod_time'] : '';
			
			$starttime = mktime(0,0,0,date('m'),date('d'),date('Y'));
			$vtime = strtotime($vod_time);
			$ch = '';
			$display_time = $vod_time;
			
			if($vtime>=$starttime&&$vtime<=$starttime+78400)
			{ 
				$ch = 'checked';
				$display_time='<font color="red">'.$vod_time.'</font>';
			}
			?>
        	<tr>
		<td nowrap="nowrap"><input type="checkbox" class="checkbox" name="ids[]" value="<?php  echo $vod_id; ?>" id="<?php  echo $vod_id; ?>" <?php  echo $ch; ?>/><label for="<?php  echo $vod_id; ?>"><?php  echo $vod_name; ?><font color="#FF0000"><?php  echo $vod_remarks; ?></font></label></td>
		<td nowrap="nowrap"><a href="?ac=list&t=<?php  echo $type_id; ?>&url=<?php  echo $var_url;?>&rid=<?php  echo $rid;?>"><?php  echo $type_name; ?></a></td>
		<td nowrap="nowrap"></td>
		<td nowrap="nowrap"><?php  echo $display_time; ?></td>
	</tr>

        <?php 
		}
	} else {
		foreach($xml->list->video as $video)
		{
			//echo gbutf8($video->id)."<br/>";
			$starttime = mktime(0,0,0,date('m'),date('d'),date('Y'));
			$vtime = strtotime($video->last);
			if($vtime>=$starttime&&$vtime<=$starttime+78400)
			{ 
				$ch = 'checked';
				$video->last='<font color="red">'.$video->last.'</font>';
			}
			else
			{
				$ch = '';	
			}
			?>
        	<tr>
		<td nowrap="nowrap"><input type="checkbox" class="checkbox" name="ids[]" value="<?php  echo $video->id; ?>" id="<?php  echo $video->id; ?>" <?php  echo $ch; ?>/><label for="<?php  echo $video->id; ?>"><?php  echo $video->name; ?><font color="#FF0000"><?php  echo $video->note; ?></font></label></td>
		<td nowrap="nowrap"><a href="?ac=list&t=<?php  echo $video->tid; ?>&url=<?php  echo $var_url;?>&rid=<?php  echo $rid;?>"><?php  echo $video->type; ?></a></td>
		<td nowrap="nowrap"><?php  echo $video->dt; ?></td>
		<td nowrap="nowrap"><?php  echo $video->last; ?></td>
	</tr>

        <?php 
		}
	}
	?>
	</form>

    <tr>
      <td colspan="4"><div class="cuspages" style="margin:0">
          <div class="pages" style="margin:0">
          共 <?php  echo($totalcount)?> 条数据 每页 <?php  echo $pagesize; ?> 条 当前 <?php  echo $currentpage?>/<?php  echo $pagecount?> 
          页码
          <a href="?ac=list&amp;rid=<?php  echo $rid;?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;pg=1&amp;url=<?php  echo $var_url?>">首页</a>
          <a href="?ac=list&amp;rid=<?php  echo $rid;?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;pg=<?php  echo ($pg-1) ?>&amp;url=<?php  echo $var_url?>">上一页</a>
          <?php  
		  	for($i=$pg-3;$i<($pg+4);$i++)
			{
				if($i<1){}
				elseif($i>$pagecount){}
				else{
			
		  ?>
          <a href='?ac=list&amp;rid=<?php  echo $rid;?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;pg=<?php  echo ($i) ?>&amp;url=<?php  echo $var_url?>'><?php  echo $i;?></a>
          <?php 
			}
			}
		  ?>
          
          <a href="?ac=list&amp;rid=<?php  echo $rid;?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;url=<?php  echo $var_url?>&amp;pg=<?php  echo ($pg+1) ?>">下一页</a>
          <a href="?ac=list&amp;rid=<?php  echo $rid;?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;url=<?php  echo $var_url?>&amp;pg=<?php  echo ($pagecount) ?>">尾页</a>&nbsp;&nbsp;
          跳转
            <input type="text" id="skip2" value="" onKeyUp="this.value=this.value.replace(/[^\d]+/,'')" style="width:26px;height:14px;"/>
            <input type="button" value="确定" class="btn" onClick="location.href='?ac=list&amp;rid=<?php  echo $rid;?>&amp;url=<?php  echo $var_url?>&amp;t=<?php  echo $t ?>&amp;h=<?php  echo $h ?>&amp;wd=<?php  echo $wd ?>&amp;pg='+$('skip2').value;"/>
          </div>
        </div></td>
    </tr>

  </table>
</div>  
    
    

	<?php 
	bottom();

}
elseif($action=="bind")
{
	echo "<select name='tid' id='tid'><option value=''>解除绑定</option>";
	$typeid=getBindedLocalId($curid);
	makeTypeOptionSelected(0,"&nbsp;|&nbsp;","",$typeid);
	echo "</select>&nbsp;&nbsp;<input type='button' class='btn' value='绑  定' onclick=\"submitBindType(getSelect_Value('tid'),'".$curid."','".$typeid."')\"/>&nbsp;&nbsp;<input type='button' class='btn' value='返  回' onclick='hideBind();'/></form>";
}
elseif($action=="bindsubmit")
{
	if(is_numeric($tid))
	{
		if(!empty($v_oldtype))
		{
			$row = $dsql->GetOne("select tid,unionid from sea_type where tid='$v_oldtype'");
			if(is_array($row)){
				$unionid=delBindId($curid,$row["unionid"]);
				$dsql->ExecuteNoneQuery("update sea_type set unionid='$unionid' where tid=".$row['tid']);
			}
		}
		$row = $dsql->GetOne("select tid,unionid from sea_type where tid='$tid'");
		if(is_array($row)){
			$unionid=addUnionid($curid,$row["unionid"]);
			$dsql->ExecuteNoneQuery("update sea_type set unionid='$unionid' where tid=".$row['tid']);
		}
		echo "bindok";
		exit;
	}else{
		$row = $dsql->GetOne("select tid,unionid from sea_type where concat(',',unionid,',') like '%,".$curid.",%'");
		if(is_array($row)){
			$unionid=delBindId($curid,$row["unionid"]);
			$dsql->ExecuteNoneQuery("update sea_type set unionid='$unionid' where tid=".$row['tid']);
		}
		echo "nobind";
		exit;
	}
}
elseif($action=="aibind")
{
	@session_write_close();
	header('Content-Type: application/json; charset=utf-8');
	require_once(sea_INC.'/ai.php');

	$bindRid = trim((string)$rid);
	$bindUrl = trim((string)$var_url);
	if($bindRid==='' || $bindUrl==='' || !preg_match('#^https?://#i', $bindUrl))
	{
		echo json_encode(array('ok'=>false,'error'=>'缺少资源库参数'), JSON_UNESCAPED_UNICODE);
		exit;
	}

	$aiCfg = deepseek_load_config();
	if($aiCfg['open'] !== 'on' || empty($aiCfg['api_key']))
	{
		echo json_encode(array('ok'=>false,'error'=>'AI 未开启或 API Key 未配置，请先在「AI 智能扩展」中设置'), JSON_UNESCAPED_UNICODE);
		exit;
	}

	// 1. 获取远端分类
	$apiUrl = $bindUrl.(strpos($bindUrl,'?')!==false?'&':'?').'ac=list';
	$content = cget($apiUrl,$isref);
	if(trim($content)=='') $content = cget($apiUrl,0);
	$remoteTypes = array();
	$json = json_decode($content, true);
	if(is_array($json) && isset($json['class']) && is_array($json['class']))
	{
		foreach($json['class'] as $ty)
		{
			$typeId = isset($ty['type_id']) ? trim((string)$ty['type_id']) : '';
			$typeName = isset($ty['type_name']) ? trim((string)$ty['type_name']) : '';
			if($typeId!=='') $remoteTypes[$bindRid.'_'.$typeId] = $typeName;
		}
	}
	else
	{
		$xml = @simplexml_load_string(xmlSafeStr($content),'SimpleXMLElement',LIBXML_NOCDATA|LIBXML_NOENT);
		if($xml && isset($xml->class))
		{
			foreach($xml->class->ty as $ty)
			{
				$typeId = trim((string)$ty['id']);
				$typeName = trim((string)$ty);
				if($typeId!=='') $remoteTypes[$bindRid.'_'.$typeId] = $typeName;
			}
		}
	}
	if(empty($remoteTypes))
	{
		echo json_encode(array('ok'=>false,'error'=>'获取远端分类失败，请确认资源库地址可访问'), JSON_UNESCAPED_UNICODE);
		exit;
	}

	// 2. 本地分类与现有绑定
	$localTypes = array();
	$dsql->SetQuery("SELECT tid,tname FROM sea_type ORDER BY tid ASC");
	$dsql->Execute('ai_local_types');
	while($row=$dsql->GetObject('ai_local_types'))
	{
		$localTypes[intval($row->tid)] = trim($row->tname);
	}
	if(empty($localTypes))
	{
		echo json_encode(array('ok'=>false,'error'=>'本地分类为空'), JSON_UNESCAPED_UNICODE);
		exit;
	}

	$unionidByTid = array();
	$currentBind = array();
	$dsql->SetQuery("SELECT tid,unionid FROM sea_type");
	$dsql->Execute('ai_bind_rows');
	while($row=$dsql->GetObject('ai_bind_rows'))
	{
		$tid = intval($row->tid);
		$unionidByTid[$tid] = (string)$row->unionid;
		foreach(explode(',',(string)$row->unionid) as $one)
		{
			$one = trim($one);
			if($one!=='') $currentBind[$one] = $tid;
		}
	}

	// 3. 仅处理未绑定的远端分类，保留已有绑定
	$pending = array();
	foreach($remoteTypes as $remoteKey=>$remoteName)
	{
		if(!isset($currentBind[$remoteKey])) $pending[$remoteKey] = $remoteName;
	}
	$skipped = count($remoteTypes) - count($pending);
	if(empty($pending))
	{
		echo json_encode(array('ok'=>true,'total'=>count($remoteTypes),'bound'=>0,'unmatched'=>0,'skipped'=>$skipped,'changed'=>0), JSON_UNESCAPED_UNICODE);
		exit;
	}

	// 4. 调用 AI 生成分类映射
	$remotePayload = array();
	foreach($pending as $k=>$v) $remotePayload[] = array('id'=>$k,'name'=>$v);
	$localPayload = array();
	foreach($localTypes as $tid=>$tname) $localPayload[] = array('tid'=>$tid,'name'=>$tname);

	$sysPrompt = '你是影视 CMS 的分类映射助手。请把资源库分类按语义映射到最合适的本地分类。只输出一个 JSON 对象，键为资源库分类 id，值为本地分类 tid（整数），无法匹配时值为 0。不要输出 JSON 以外的任何内容。';
	$userPrompt = "资源库分类：\n".json_encode($remotePayload, JSON_UNESCAPED_UNICODE)
		."\n\n本地分类：\n".json_encode($localPayload, JSON_UNESCAPED_UNICODE)
		."\n\n要求：\n1. 按语义选择最接近的本地分类，例如「动作片」可映射到「动作」；\n2. 允许多个资源库分类映射到同一个本地分类；\n3. 确实无法对应的填 0，不要勉强匹配。";

	$aiResp = deepseek_chat(array(
		array('role'=>'system','content'=>$sysPrompt),
		array('role'=>'user','content'=>$userPrompt),
	),0.1,true,120);
	if(!$aiResp['ok'])
	{
		echo json_encode(array('ok'=>false,'error'=>'AI 调用失败：'.$aiResp['error']), JSON_UNESCAPED_UNICODE);
		exit;
	}
	$jsonText = deepseek_extract_json($aiResp['content']);
	$map = $jsonText ? json_decode($jsonText, true) : null;
	if(!is_array($map))
	{
		echo json_encode(array('ok'=>false,'error'=>'AI 返回内容无法解析为分类映射'), JSON_UNESCAPED_UNICODE);
		exit;
	}

	// 5. 校验映射并写入 sea_type.unionid
	$finalMap = array();
	$bound = 0;
	$unmatched = 0;
	foreach($pending as $remoteKey=>$remoteName)
	{
		$target = 0;
		if(isset($map[$remoteKey]))
		{
			$target = intval($map[$remoteKey]);
		}
		else
		{
			$shortId = substr($remoteKey, strlen($bindRid)+1);
			if(isset($map[$shortId])) $target = intval($map[$shortId]);
		}
		if($target>0 && !isset($localTypes[$target])) $target = 0;
		$finalMap[$remoteKey] = $target;
		if($target>0) $bound++; else $unmatched++;
	}

	$affected = array();
	foreach($finalMap as $remoteKey=>$target)
	{
		if($target>0) $affected[$target] = true;
	}
	$changed = 0;
	foreach($affected as $tid=>$tmp)
	{
		$kept = array();
		$oldUnionid = isset($unionidByTid[$tid]) ? $unionidByTid[$tid] : '';
		foreach(explode(',',$oldUnionid) as $one)
		{
			$one = trim($one);
			if($one!=='' && !isset($finalMap[$one])) $kept[] = $one;
		}
		foreach($finalMap as $remoteKey=>$target)
		{
			if($target==$tid && !in_array($remoteKey,$kept)) $kept[] = $remoteKey;
		}
		$newUnionid = implode(',',$kept);
		if($newUnionid !== trim($oldUnionid))
		{
			$dsql->ExecuteNoneQuery("UPDATE sea_type SET unionid='".addslashes($newUnionid)."' WHERE tid=".intval($tid));
			$changed++;
		}
	}

	echo json_encode(array(
		'ok'=>true,
		'total'=>count($remoteTypes),
		'bound'=>$bound,
		'unmatched'=>$unmatched,
		'skipped'=>$skipped,
		'changed'=>$changed
	), JSON_UNESCAPED_UNICODE);
	exit;
}
elseif($action=="select")
{
	if(empty($ids))
	{
		ShowMsg("请选择采集数据","-1");
		exit();
	}
	$a_ids = implode(',',$ids);
	if($rid=='seazmt3zz2cmszmt')
	{
		$weburl=$var_url."?s=plus-api-xml-cms-max-vodids-".$a_ids;
	}
	else
	{
		$weburl=$var_url.(strpos($var_url,'?')!==false?"&":"?")."ac=videolist&ressite=".$ressite."&ids=".$a_ids;
	}
	intoDatabase($weburl,"select");
}
elseif($action=="day")
{
	$page = $pg;
	if($rid=='seazmt3zz2cmszmt')
	{
		$weburl=$var_url."?s=plus-api-xml-cms-max-cid--h-24-p-{$page}";
	}
	else
	{
		$weburl=$var_url.(strpos($var_url,'?')!==false?"&":"?")."ac=videolist&rid=".$ressite."&t=0&h=24&pg=".$page;
	}
	
	intoDatabase($weburl,"day");
}elseif($action=="week")
{
	$page = $pg;
	if($rid=='seazmt3zz2cmszmt')
	{
		$weburl=$var_url."?s=plus-api-xml-cms-max-cid--h-168-p-{$page}";
	}
	else
	{
		$weburl=$var_url.(strpos($var_url,'?')!==false?"&":"?")."ac=videolist&rid=".$ressite."&t=0&h=168&pg=".$page;
	}
	//echo $weburl;
	
	intoDatabase($weburl,"week");
}
elseif($action=="type")
{
	$page = $pg;
	if($rid=='seazmt3zz2cmszmt')
	{
		$weburl=$var_url."?s=plus-api-xml-cms-max-cid-{$t}-h--p-{$page}";
	}
	else
	{
		$weburl=$var_url.(strpos($var_url,'?')!==false?"&":"?")."ac=videolist&rid=".$ressite."&t=".$t."&pg=".$page;
	}
	intoDatabase($weburl,"type");
}
elseif($action=="all")
{
	$page = $pg;
	if($rid=='seazmt3zz2cmszmt')
	{
		$weburl=$var_url."?s=plus-api-xml-cms-max-cid-{$t}-h--p-{$page}";
	}
	else
	{
		$weburl=$var_url.(strpos($var_url,'?')!==false?"&":"?")."ac=videolist&rid=".$ressite."&pg=".$page;
	}
	intoDatabase($weburl,"all");
}
else
{
	top();
	echo cget($reslibMainSite."resindex.html",$isref);
	bottom();
}

function getBindedLocalId($libId)
{
	global $dsql;
	$row = $dsql->GetOne("select count(*) as dd from sea_type where unionid<>''");
	if(!is_array($row)) return '';
	$sqlStr="select tid,unionid from sea_type where unionid<>''";
	$dsql->SetQuery($sqlStr);
	$dsql->Execute('unionid_list');
	while($row=$dsql->GetObject('unionid_list'))
	{
		$unionArray=explode(",",$row->unionid); $arrayLen2=count($unionArray);
		for($i=0;$i<$arrayLen2;$i++){
			if (trim($unionArray[$i])==trim($libId)) return $row->tid;
		}
	}
}

function delBindId($bindid,$unionId)
{
	if(empty($unionId)){
		return $bindid;
	}else{
		if(strpos(",,".$unionId.",,",",".$bindid.",")) return ltrim(rtrim(str_replace(",".$bindid.",",",",",".$unionId.","),","),","); else return $unionId;
	}
}

function addUnionid($bindid,$unionId)
{
	if(empty($unionId)){
		return $bindid;
	}else{
		if(strpos(",".$unionId.",",",".$bindid.",")) return $unionId; else return $unionId.",".$bindid;
	}
}

function getBindedLibIds()
{
	global $dsql;
	$libIds="";
	$sqlStr="select tid,unionid from sea_type where unionid<>''";
	$dsql->SetQuery($sqlStr);
	$dsql->Execute('unionid_list');
	while($row=$dsql->GetObject('unionid_list'))
	{
		$libIds.=$row->unionid.',';
	}
	$libIds=rtrim($libIds,',');
	return $libIds;
}

function intoDatabase($url,$gtype)
{
	@session_write_close();
	global $dsql,$col,$cfg_gatherset,$backurl,$gatherWaitTime,$ressite,$var_url,$action,$isref,$pg;
	$content=cget($url,$isref);
	$content=filterChar($content);
	
	// 自动检测数据格式
	$is_json = false;
	$json_data = null;
	$xml = null;
	
	// 尝试解析为 JSON
	$json_data = json_decode($content, true);
	if (json_last_error() === JSON_ERROR_NONE && isset($json_data['code'])) {
		$is_json = true;
	} else {
		// JSON 解析失败，尝试 XML
		$xml = simplexml_load_string(xmlSafeStr($content), 'SimpleXMLElement',LIBXML_NOCDATA|LIBXML_NOENT); 
		if(!$xml){	$xml =  simplexml_load_string(xmlSafeStr(cget($url,0), 'SimpleXMLElement',LIBXML_NOCDATA|LIBXML_NOENT)); }
		if(!$xml){ 
			// 两种格式都失败
			echo '<strong>采集失败，错误信息如下：</strong><br><br>地址：'.$url.'<br>原因：①网络中断 ②数据格式错误<br>解决：①重试 ②检查资源库地址<br><br><strong><a href="admin_reslib.php">点击此处继续</a></strong>';
			exit;
		}
	}
	
	echo "<div style='font-size:13px'><font color=red>资源库视频采集开始：</font><br>";
	if ($gtype=="type" || $gtype=="day"|| $gtype=="week"|| $gtype=="all") 
	{
		if(empty($pg))
			$page=1;
		else
			$page=$pg;
	}
	
	$pagecount = 0;
	$pagesize = 0;
	$recordcount = 0;
	
	if ($is_json) {
		// 处理 JSON 格式数据
		$pagecount = isset($json_data['pagecount']) ? $json_data['pagecount'] : 1;
		$pagesize = isset($json_data['limit']) ? $json_data['limit'] : 20;
		$recordcount = isset($json_data['total']) ? $json_data['total'] : 0;
		
		if (isset($json_data['list']) && is_array($json_data['list'])) {
			foreach($json_data['list'] as $video)
			{
				$xmltid = isset($video['type_id']) ? $video['type_id'] : 0; // 影片分类id
				$name = isset($video['vod_name']) ? $video['vod_name'] : ''; // 影片名称
				$localId = getBindedLocalId($ressite.'_'.$xmltid); // 入库后本地id
				
				if(!empty($name))
				{
					echo $col->json_db($video,$localId);
				}//if $title
				@ob_flush();
				@flush();
			}//foreach
		}
	} else {
		// 处理 XML 格式数据
		$temparr=array();
		$temparr=getrulevaluearr($content,'v');
		$pagecount = $xml->list['pagecount'];
		$pagesize = $xml->list['pagesize'];
		$recordcount = $xml->list['recordcount'];
		
		foreach($xml->list->video as $video)
		{
			$xmltid =  $video->tid;//影片分类id
			$name =  $video->name;//影片名称
			$localId = getBindedLocalId($ressite.'_'.$xmltid);//入库后本地id
			$data = "$$".$video->dl->dd;
			if(!empty($name)&&!empty($data))
			{
				echo $col->xml_db($video,$localId);
			}//if $title
			@ob_flush();
			@flush();
		}//foreach
	}
	
	if ($action=="select") exit("<script>alert('恭喜，全部搞定');location.href='".urldecode($backurl)."';</script>");
	if($page==$pagecount OR $pagecount==0)
	{
		if ($action=="day" || $action=="type"|| $action=="all"||$action=="week")
		{ 
			RWCache('collect_xml',NULL);
			exit("<script>alert('恭喜，全部搞定');location.href='".urldecode($backurl)."'</script>");
		}
	}else{
		echo "<br/>暂停".$gatherWaitTime."秒--<font color=red>即将开始同步第".($page+1)."/".$pagecount."页</font><br/></div>";
		if ($action=="day"){
			RWCache('collect_xml',"admin_reslib.php?action=day&rid=".$ressite."&pg=".($page+1)."&url=".$var_url."&backurl=".urlencode($backurl));
			echo "<script language=\"javascript\">setTimeout(\"makeNextPage();\",".$gatherWaitTime."000);function makeNextPage(){location.href='?action=day&rid=".$ressite."&pg=".($page+1)."&url=".$var_url."&backurl=".urlencode($backurl)."';}</script>";
		}elseif ($action=="week"){
			RWCache('collect_xml',"admin_reslib.php?action=week&rid=".$ressite."&pg=".($page+1)."&url=".$var_url."&backurl=".urlencode($backurl));
			echo "<script language=\"javascript\">setTimeout(\"makeNextPage();\",".$gatherWaitTime."000);function makeNextPage(){location.href='?action=week&rid=".$ressite."&pg=".($page+1)."&url=".$var_url."&backurl=".urlencode($backurl)."';}</script>";
		}elseif ($action=="type"){
			RWCache('collect_xml',"admin_reslib.php?action=type&rid=".$ressite."&pg=".($page+1)."&t=".$xmltid."&url=".$var_url."&backurl=".urlencode($backurl));
			echo "<script language=\"javascript\">setTimeout(\"makeNextPage();\",".$gatherWaitTime."000);function makeNextPage(){location.href='?action=type&rid=".$ressite."&pg=".($page+1)."&t=".$xmltid."&url=".$var_url."&backurl=".urlencode($backurl)."';}</script>";
		}elseif ($action=="all"){
			RWCache('collect_xml',"admin_reslib.php?action=all&rid=".$ressite."&pg=".($page+1)."&url=".$var_url."&backurl=".urlencode($backurl));
			echo "<script language=\"javascript\">setTimeout(\"makeNextPage();\",".$gatherWaitTime."000);function makeNextPage(){location.href='?action=all&rid=".$ressite."&pg=".($page+1)."&url=".$var_url."&backurl=".urlencode($backurl)."';}</script>";
		}
	}
}

function getrulevalue($content,$str)
{
	if(!empty($content) && !empty($str)){
		$labelRule = buildregx("<".$str.">(.*?)"."</".$str.">","is");
		preg_match_all($labelRule,$content,$ar);
		return $ar[1][0];
	}
}

function getrulevaluearr($content,$str)
{
	if(!empty($content) && !empty($str)){
		$labelRule = buildregx("<".$str.">(.*?)"."</".$str.">","is");
		preg_match_all($labelRule,$content,$ar);
		return $ar[1];
	}
}

function unescapseCode($str)
{
	if(empty($str)) return ""; else return urldecode(UnicodeUrl2Gbk($str));
}

function replaceCDATA($str)
{
	return str_replace(']]>','',str_replace('<![CDATA[','',$str));
}

function top()
{
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>资源库采集程序</title>
<link rel="stylesheet" type="text/css" href="img/res.css"/>
<link href="img/admin.css" rel="stylesheet" type="text/css" />
<script src="js/common.js"></script>
<script src="js/main.js?v=20261118"></script>
<script src="js/e-admin.js"></script>
<style type="text/css">
	input{height:16px;}
	.tb2 td{padding:2px 5px 2px 5px;height:25px}
	.tb2 .thc{text-align:center;line-height:30px; background-color: #F5F7F8;font-size:18px;font-weight:bold;color:#000}
	.tb2 .thr{text-align:center;height:20px}
	.label{padding:0;width:150px;text-align:right;border-right:1px solid #DEEFFA;}
	.btn{height:22px}
	.red{color:red}
	.blue{color:blue}
	.gray{color:gray}
	.ul li{float:left;margin:3px;width:150px}
	h3{padding:0;margin:0;font-size: 13px;background:none;border:none}
</style>
</head>
<body bgcolor="#F7FBFF">
<div class="container" id="cpcontainer">
<?php 
}

function bottom()
{
?>
</div>
<div align=center><?php 

	global $dsql,$starttime;
	echo "<div align=center>";
	$starttime = explode(' ', $starttime);
	$endtime = explode(' ', microtime()); 
	$timeuse=round(($endtime[0]+($endtime[1]-$starttime[1])-$starttime[0]),0)/1000000;
	echo "</div><div class=\"bottom\"><table width=\"100%\" cellspacing=\"2\"><tr><td align=\"center\">本页面用时".
	$timeuse."秒,共执行6次数据查询</td></tr><tr><td align=\"center\"><a target=\"_blank\" href=\"http://www.seacms.net/\">POWER BY SEACMS</a></td></tr></table></div>\n</body>\n</html>";


?></div>
</body>
</html> 
<?php 
}
?>