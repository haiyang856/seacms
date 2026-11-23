<?php
header('Content-Type:text/html;charset=utf-8');
require_once(dirname(__FILE__)."/config.php");
CheckPurview();
require_once(sea_INC.'/ai.php');

// ====== 批量 AI：获取待处理视频列表 ======
if($action=="batch_list")
{
	header('Content-Type: application/json; charset=utf-8');
	$cfg = deepseek_load_config();
	if($cfg['open'] !== 'on' || empty($cfg['api_key']))
	{
		echo json_encode(array('ok'=>false, 'error'=>'AI 未开启或 API Key 未配置'), JSON_UNESCAPED_UNICODE);
		exit;
	}
	$dsql->SetQuery("SELECT v_id,v_name FROM sea_data WHERE v_ai=0 AND v_name<>'' AND v_recycled=0 ORDER BY v_id ASC");
	$dsql->Execute("bl");
	$list = array();
	while($row = $dsql->GetArray("bl"))
	{
		$list[] = array('v_id'=>intval($row['v_id']), 'v_name'=>trim($row['v_name']));
	}
	echo json_encode(array('ok'=>true, 'total'=>count($list), 'list'=>$list), JSON_UNESCAPED_UNICODE);
	exit;
}

// ====== 批量 AI：处理单条视频 ======
if($action=="batch_process")
{
	header('Content-Type: application/json; charset=utf-8');
	$vid = isset($_POST['v_id']) ? intval($_POST['v_id']) : 0;
	if($vid <= 0) { echo json_encode(array('ok'=>false,'error'=>'无效 v_id')); exit; }

	// 查视频
	$vrow = $dsql->GetOne("SELECT v_id,v_name,tid FROM sea_data WHERE v_id={$vid}");
	if(!$vrow) { echo json_encode(array('ok'=>false,'error'=>'视频不存在')); exit; }
	$vrow=is_array($vrow)?$vrow:array(); if(intval($vrow['v_ai']) === 1) { echo json_encode(array('ok'=>true,'skipped'=>true,'msg'=>'已标记处理完成')); exit; }

	$vname = trim($vrow['v_name']);
	if($vname === '') { echo json_encode(array('ok'=>false,'error'=>'视频名称为空')); exit; }

	// 调用 AI
	$result = deepseek_fetch_video($vname);
	if(!$result['ok'])
	{
		echo json_encode(array('ok'=>false,'error'=>$result['error']), JSON_UNESCAPED_UNICODE);
		exit;
	}
	$d = $result['data'];

	$v_director    = addslashes($d['directors']);
	$v_actor       = addslashes($d['actors']);
	$v_tags        = addslashes($d['keywords']);
	$v_jq          = addslashes($d['jqtype']);
	$v_publishyear = intval($d['release_year']);
	$v_lang        = addslashes($d['language']);
	$v_publisharea = addslashes($d['region']);
	$v_douban      = floatval($d['douban_score']);
	$v_content     = addslashes($d['description']);

	// 更新 sea_data
	$upd = "UPDATE sea_data SET "
		."v_director='{$v_director}',"
		."v_actor='{$v_actor}',"
		."v_tags='{$v_tags}',"
		."v_jq='{$v_jq}',"
		."v_publishyear={$v_publishyear},"
		."v_lang='{$v_lang}',"
		."v_publisharea='{$v_publisharea}',"
		."v_douban={$v_douban},"
		."v_ai=1 "
		."WHERE v_id={$vid}";
	if(!$dsql->ExecuteNoneQuery($upd))
	{
		echo json_encode(array('ok'=>false,'error'=>'SQL更新失败: '.$dsql->GetError()), JSON_UNESCAPED_UNICODE);
		exit;
	}

	// 更新 sea_content
	if($v_content !== '')
	{
		$dsql->ExecuteNoneQuery("UPDATE sea_content SET body='{$v_content}' WHERE v_id={$vid}");
	}

	echo json_encode(array(
		'ok'=>true,
		'skipped'=>false,
		'msg'=>'成功',
		'v_id'=>$vid,
		'v_name'=>$vname,
		'directors'=>$d['directors'],
		'jqtype'=>$d['jqtype']
	), JSON_UNESCAPED_UNICODE);
	exit;
}

// ====== 重置所有 v_ai=0 ======
if($action=="batch_reset")
{
	header('Content-Type: application/json; charset=utf-8');
	$cnt = $dsql->GetOne("SELECT COUNT(*) AS c FROM sea_data WHERE v_recycled=0 AND v_name<>''");
	$cnt=is_array($cnt)?$cnt:array(); $total = intval($cnt['c']);
	if($total === 0){ echo json_encode(array('ok'=>true,'total'=>0)); exit; }
	$dsql->ExecuteNoneQuery("UPDATE sea_data SET v_ai=0 WHERE v_recycled=0 AND v_name<>''");
	echo json_encode(array('ok'=>true,'total'=>$total), JSON_UNESCAPED_UNICODE);
	exit;
}

if($action=="set")
{
	$ai_open            = isset($_POST['ai_open'])            ? $_POST['ai_open']            : 'off';
	$ai_provider        = isset($_POST['ai_provider'])        ? $_POST['ai_provider']        : 'custom';
	$ai_api_url         = trim($_POST['ai_api_url']);
	$ai_api_key         = $_POST['ai_api_key'];
	$ai_model           = trim($_POST['ai_model']);
	$ai_temperature     = trim($_POST['ai_temperature']);
	$ai_max_tokens      = trim($_POST['ai_max_tokens']);
	$ai_search_correct  = isset($_POST['ai_search_correct'])  ? $_POST['ai_search_correct']  : 'off';
	$ai_recommend       = isset($_POST['ai_recommend'])       ? $_POST['ai_recommend']       : 'off';
	$ai_limit_daily     = isset($_POST['ai_limit_daily'])     ? max(0, intval($_POST['ai_limit_daily']))     : 0;
	$ai_limit_minute    = isset($_POST['ai_limit_minute'])    ? max(0, intval($_POST['ai_limit_minute']))    : 0;

	// 提供商预设自动填充
	if($ai_provider !== 'custom')
	{
		$providers = array(
			'deepseek' => array('api_url'=>'https://api.deepseek.com/v1',                          'model'=>'deepseek-chat'),
			'openai'   => array('api_url'=>'https://api.openai.com/v1',                            'model'=>'gpt-4o-mini'),
			'qwen'     => array('api_url'=>'https://dashscope.aliyuncs.com/compatible-mode/v1',   'model'=>'qwen-turbo'),
			'moonshot' => array('api_url'=>'https://api.moonshot.cn/v1',                           'model'=>'moonshot-v1-8k'),
			'volcengine'=>array('api_url'=>'https://ark.cn-beijing.volces.com/api/v3',             'model'=>''),
		);
		if(isset($providers[$ai_provider]))
		{
			if(empty($ai_api_url))  $ai_api_url  = $providers[$ai_provider]['api_url'];
			if(empty($ai_model))    $ai_model    = $providers[$ai_provider]['model'];
		}
	}

	// 默认值兜底
	if($ai_api_url     == '') $ai_api_url     = 'https://api.deepseek.com/v1';
	if($ai_model       == '') $ai_model       = 'deepseek-chat';
	if($ai_temperature == '') $ai_temperature = '0.7';
	if($ai_max_tokens  == '') $ai_max_tokens  = '800';

	// 读取旧配置以保留当前用量，再加锁原子写入
	$__cfg_path = deepseek_ai_config_path();
	$__lock = deepseek_ai_lock();
	$__vars = deepseek_ai_read_vars($__cfg_path);

	$__vars['ai_open']           = $ai_open;
	$__vars['ai_provider']       = $ai_provider;
	$__vars['ai_api_url']        = $ai_api_url;
	$__vars['ai_api_key']        = $ai_api_key;
	$__vars['ai_model']          = $ai_model;
	$__vars['ai_temperature']    = $ai_temperature;
	$__vars['ai_max_tokens']     = $ai_max_tokens;
	$__vars['ai_search_correct'] = $ai_search_correct;
	$__vars['ai_recommend']      = $ai_recommend;
	$__vars['ai_limit_daily']    = $ai_limit_daily;
	$__vars['ai_limit_minute']   = $ai_limit_minute;

	// 当前用量不从表单覆盖，保留文件中的值
	if(!isset($__vars['ai_usage_date']))       $__vars['ai_usage_date'] = '';
	if(!isset($__vars['ai_usage_day']))        $__vars['ai_usage_day'] = 0;
	if(!isset($__vars['ai_usage_minute_key'])) $__vars['ai_usage_minute_key'] = '';
	if(!isset($__vars['ai_usage_minute']))     $__vars['ai_usage_minute'] = 0;

	$__saved = deepseek_ai_write_vars($__cfg_path, $__vars);
	if($__lock) { flock($__lock, LOCK_UN); fclose($__lock); }
	if(!$__saved)
	{
		ShowMsg("保存失败，请检查 data/admin/ai.php 是否可写!","admin_ai.php");
		exit;
	}
	ShowMsg("成功保存设置!","admin_ai.php");
	exit;
}

// 读取配置（新变量优先，旧变量 fallback）
$ai_open           = "off";
$ai_provider       = "custom";
$ai_api_url        = "https://api.deepseek.com/v1";
$ai_api_key        = "";
$ai_model          = "deepseek-chat";
$ai_temperature    = "0.7";
$ai_max_tokens     = "800";
$ai_search_correct = "off";
$ai_recommend      = "off";
$ai_limit_daily    = 0;
$ai_limit_minute   = 0;
$ai_usage_date     = "";
$ai_usage_day      = 0;
$ai_usage_minute_key = "";
$ai_usage_minute   = 0;

// 配置文件：优先 /data/admin/ai.php，兼容旧 deepseek.php
$__cfg_file = file_exists("../data/admin/ai.php") ? "../data/admin/ai.php" : "../data/admin/deepseek.php";

$__cfg = array();
if(file_exists($__cfg_file))
{
	ob_start();
	include($__cfg_file);
	ob_end_clean();
	$__cfg['ai_open']           = isset($ai_open)           ? $ai_open           : 'off';
	$__cfg['ai_provider']       = isset($ai_provider)       ? $ai_provider       : 'custom';
	$__cfg['ai_api_url']        = isset($ai_api_url)        ? $ai_api_url        : 'https://api.deepseek.com/v1';
	$__cfg['ai_api_key']        = isset($ai_api_key)        ? $ai_api_key        : '';
	$__cfg['ai_model']          = isset($ai_model)          ? $ai_model          : 'deepseek-chat';
	$__cfg['ai_temperature']    = isset($ai_temperature)    ? $ai_temperature    : '0.7';
	$__cfg['ai_max_tokens']     = isset($ai_max_tokens)     ? $ai_max_tokens     : '800';
	$__cfg['ai_search_correct'] = isset($ai_search_correct) ? $ai_search_correct : 'off';
	$__cfg['ai_recommend']      = isset($ai_recommend)      ? $ai_recommend      : 'off';
	$__cfg['ai_limit_daily']    = isset($ai_limit_daily)    ? max(0, intval($ai_limit_daily))    : 0;
	$__cfg['ai_limit_minute']   = isset($ai_limit_minute)   ? max(0, intval($ai_limit_minute))   : 0;
	$__cfg['ai_usage_date']     = isset($ai_usage_date)     ? $ai_usage_date     : '';
	$__cfg['ai_usage_day']      = isset($ai_usage_day)      ? max(0, intval($ai_usage_day))      : 0;
	$__cfg['ai_usage_minute_key'] = isset($ai_usage_minute_key) ? $ai_usage_minute_key : '';
	$__cfg['ai_usage_minute']   = isset($ai_usage_minute)   ? max(0, intval($ai_usage_minute))   : 0;

	// 旧变量名 fallback
	if(($__cfg['ai_api_url']=='https://api.deepseek.com/v1' || $__cfg['ai_api_url']=='' ) && isset($deepseek_api_url) && $deepseek_api_url!='')
		$__cfg['ai_api_url'] = $deepseek_api_url;
	if($__cfg['ai_model']=='deepseek-chat' && isset($deepseek_model) && $deepseek_model!='')
		$__cfg['ai_model'] = $deepseek_model;
	if($__cfg['ai_api_key']=='' && isset($deepseek_api_key))
		$__cfg['ai_api_key'] = $deepseek_api_key;
	if($__cfg['ai_temperature']=='0.7' && isset($deepseek_temperature) && $deepseek_temperature!='')
		$__cfg['ai_temperature'] = $deepseek_temperature;
	if(isset($deepseek_open))
		$__cfg['ai_open'] = $deepseek_open;

	extract($__cfg);
}

// 当前用量展示（跨天/跨分钟自动视为 0，下次调用时正式重置）
$__usageDayShow    = ($ai_usage_date === date('Y-m-d')) ? intval($ai_usage_day) : 0;
$__usageMinuteShow = ($ai_usage_minute_key === date('Y-m-d H:i')) ? intval($ai_usage_minute) : 0;

// 提供商预设 → 判断当前配置属于哪个提供商（用于下拉框选中）
$__providers = array(
	'deepseek'   => 'https://api.deepseek.com/v1',
	'openai'     => 'https://api.openai.com/v1',
	'qwen'       => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
	'moonshot'   => 'https://api.moonshot.cn/v1',
	'volcengine' => 'https://ark.cn-beijing.volces.com/api/v3',
);
$__matched = 'custom';
foreach($__providers as $p=>$url)
{
	if(rtrim($ai_api_url,'/') === $url) { $__matched = $p; break; }
}
// 如果用户之前手动选过 provider 且保存了，保留原值
if($ai_provider !== 'custom' && isset($__providers[$ai_provider]))
{
	$__matched = $ai_provider;
}
$ai_provider = $__matched;

// 统计未处理视频数 & 总视频数
$__ai_count = 0;
$__ai_total = 0;
if(isset($dsql))
{
	$__row = $dsql->GetOne("SELECT COUNT(*) AS c FROM sea_data WHERE v_ai=0 AND v_name<>'' AND v_recycled=0");
	$__row=is_array($__row)?$__row:array(); $__ai_count = intval($__row['c']);
	$__row2 = $dsql->GetOne("SELECT COUNT(*) AS c FROM sea_data WHERE v_name<>'' AND v_recycled=0");
	$__row2=is_array($__row2)?$__row2:array(); $__ai_total = intval($__row2['c']);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>AI 接口设置</title>
<link  href="img/style.css" rel="stylesheet" type="text/css" />
<script src="js/common.js" type="text/javascript"></script>
<script src="js/main.js" type="text/javascript"></script>
</head>
<body>
<script type="text/JavaScript">if(parent.$('admincpnav')) parent.$('admincpnav').innerHTML='后台首页&nbsp;&raquo;&nbsp;管理员&nbsp;&raquo;&nbsp;AI 智能扩展';</script>
<div class="r_main">
  <div class="r_content">
    <div class="r_content_1">
<form action="admin_ai.php?action=set" method="post" id="ai_form">
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tb_style">
<tbody>
<tr class="thead">
<td colspan="5" class="td_title">AI 大模型接口设置</td>
</tr>
<tr>
<td width="80%" align="left" height="32" class="td_border">
功能开关：
<input type="radio" name="ai_open" value="off" <?php if($ai_open=='off') echo 'checked';?>>关闭
&nbsp;&nbsp;
<input type="radio" name="ai_open" value="on" <?php if($ai_open=='on') echo 'checked';?>>开启
<tr>
<td width="80%" align="left" height="32" class="td_border">
提供商预设：
<select name="ai_provider" id="ai_provider" onchange="aiProviderChange()">
<option value="custom"     <?php if($ai_provider=='custom')     echo 'selected';?>>自定义（自建/中转）</option>
<option value="deepseek"   <?php if($ai_provider=='deepseek')   echo 'selected';?>>DeepSeek</option>
<option value="openai"     <?php if($ai_provider=='openai')     echo 'selected';?>>OpenAI</option>
<option value="qwen"       <?php if($ai_provider=='qwen')       echo 'selected';?>>通义千问（阿里云 DashScope）</option>
<option value="moonshot"   <?php if($ai_provider=='moonshot')   echo 'selected';?>>Moonshot 月之暗面</option>
<option value="volcengine" <?php if($ai_provider=='volcengine') echo 'selected';?>>火山方舟（字节跳动）</option>
</select>
 &nbsp;* 选择后自动填入 Base URL 和默认模型，可手动覆盖
</td>
</tr>
<tr>
<td width="80%" align="left" height="32" class="td_border">
API Key：<input name="ai_api_key" value="<?php echo htmlspecialchars($ai_api_key);?>" style="width:340px;" placeholder="sk-xxxxxxxxxxxx">
</td>
</tr>
<tr>
<td width="80%" align="left" height="32" class="td_border">
Base URL：<input name="ai_api_url" id="ai_api_url" value="<?php echo htmlspecialchars($ai_api_url);?>" style="width:340px;">
 &nbsp;* 例如 https://api.deepseek.com/v1  （系统自动补 /chat/completions）
</td>
</tr>
<tr>
<td width="80%" align="left" height="32" class="td_border">
模型名称：<input name="ai_model" id="ai_model" value="<?php echo htmlspecialchars($ai_model);?>" style="width:240px;">
 &nbsp;* 常用值：deepseek-chat / deepseek-reasoner / gpt-4o-mini / qwen-turbo / moonshot-v1-8k
</td>
</tr>
<tr>
<td width="80%" align="left" height="32" class="td_border">
采样温度：<input name="ai_temperature" value="<?php echo htmlspecialchars($ai_temperature);?>" style="width:80px;">
 &nbsp;* 0 ~ 2，默认 0.7，越接近 0 输出越稳定；关键词纠正建议 0.0
</td>
</tr>
<tr>
<td width="80%" align="left" height="32" class="td_border">
Max Tokens（单次请求上限）：<input name="ai_max_tokens" value="<?php echo htmlspecialchars($ai_max_tokens);?>" style="width:80px;">
 &nbsp;* 默认 800；影视资料自动翻倍到 1600 以防 description 被截断。越大越贵。
</td>
</tr>
<tr class="thead">
<td colspan="5" class="td_title">AI 搜索辅助</td>
</tr>
<tr>
<td width="80%" align="left" height="32" class="td_border">
AI搜索辅助：
<input type="radio" name="ai_search_correct" value="off" <?php if($ai_search_correct=='off') echo 'checked';?>>关闭
&nbsp;&nbsp;
<input type="radio" name="ai_search_correct" value="on" <?php if($ai_search_correct=='on') echo 'checked';?>>开启
 &nbsp;* 前台搜索时自动纠正错别字 如"流量地球" → "流浪地球"
</td>
</tr>
<tr class="thead">
<td colspan="5" class="td_title">AI 影片推荐</td>
</tr>
<tr>
<td width="80%" align="left" height="32" class="td_border">
AI 影片推荐：
<input type="radio" name="ai_recommend" value="off" <?php if($ai_recommend=='off') echo 'checked';?>>关闭
&nbsp;&nbsp;
<input type="radio" name="ai_recommend" value="on" <?php if($ai_recommend=='on') echo 'checked';?>>开启
 &nbsp;* 开启后前台右下角出现 AI 推荐浮标，访客点击即可对话获取影片推荐（需同时开启上方「功能开关」）
</td>
</tr>
<tr class="thead">
<td colspan="5" class="td_title">前台 AI 调用限额</td>
</tr>
<tr>
<td width="80%" align="left" height="32" class="td_border">
每日总限额：<input name="ai_limit_daily" value="<?php echo intval($ai_limit_daily);?>" style="width:80px;"> 次
 &nbsp;* 0 表示不限额；按自然日统计，仅限前台 AI 调用
</td>
</tr>
<tr>
<td width="80%" align="left" height="32" class="td_border">
每分钟限额：<input name="ai_limit_minute" value="<?php echo intval($ai_limit_minute);?>" style="width:80px;"> 次
 &nbsp;* 0 表示不限额；按自然分钟统计，仅限前台 AI 调用
</td>
</tr>
<tr>
<td width="80%" align="left" height="32" class="td_border">
当前用量：今日 <b><?php echo $__usageDayShow;?></b> 次；本分钟 <b><?php echo $__usageMinuteShow;?></b> 次
<br><span style="color:#888;">每次实际 AI 请求计 1 次；命中缓存不计数；管理后台调用不限额、不计数。</span>
</td>
</tr>
<tr>
<td width="10%" align="left" height="32" class="td_border">
<input type="submit" value="确 认" class="btn">
</td>
</tr>
<tr>
<td width="90%" align="left" height="32" class="td_border"><div style="padding: 10px;border: 0;border-radius: 4px;font-size: 13px;background-color: #eef5f4;">
* 支持所有 OpenAI 兼容接口，不限于 DeepSeek / OpenAI。只需改 Base URL + Model + API Key 即可切换。<br>
* 如果保存无效，请检查 /data/admin/ai.php 文件权限是否可写。
</div></td>
</tr>
</tbody></table>
</form>

<!-- 批量 AI 处理视频数据 -->
<div style="margin-top:15px;border:1px solid #b6d8d0;border-radius:4px;background:#eef5f4;padding:15px;">
<strong style="font-size:15px;">🤖 批量 AI 处理视频数据</strong>
<div style="margin-top:8px;font-size:13px;color:#555;">
系统将自动遍历视频，调用 AI 接口补全导演/演员/剧情分类/关键词/年份/地区/豆瓣评分/影片介绍等资料，成功后标记，下次运行自动跳过。
</div>
<div style="margin-top:12px;">
待处理：<span style="color:#e67e22;font-weight:bold;"><?php echo $__ai_count;?></span> 条
&nbsp;&nbsp;
<input type="button" name="ai_batch_btn" value="🚀 执行批量 AI 处理" class="btn" style="background:#e67e22;border-color:#d35400;color:#fff;font-weight:bold;padding:8px 18px;" onclick="runAiBatch()">
&nbsp;&nbsp;
<input type="button" name="ai_reset_all_btn" value="🔄 重新处理所有数据" class="btn" style="background:#bb3754;border-color:#9d354b;color:#fff;font-weight:bold;padding:8px 18px;" onclick="resetAllAiBatch(<?php echo $__ai_total;?>)">
</div>
</div>

<script type="text/javascript">
/* ============ 批量 AI 悬浮进度条 ============ */
var _aiBatch = null;

function runAiBatch()
{
	var cnt = <?php echo $__ai_count;?>;
	if(cnt === 0){
		alert('没有需要处理的视频！所有视频都已标记 v_ai=1。');
		return;
	}
	if(!confirm('确认开始批量 AI 处理？\n\n待处理: '+cnt+' 条\n执行顺序: id 从小到大\n已处理过的会自动跳过\n\n注意: 每条调用 AI 接口，耗时较长、消耗 token 额度。')) return;

	_aiBatch = {
		list: [], idx: 0, total: 0,
		okCount: 0, errCount: 0, skipCount: 0,
		paused: false, abort: false,
		errList: []
	};
	showAiBatchOverlay();
	fetchAiBatchList();
}

function showAiBatchOverlay()
{
	var mask = document.createElement('div');
	mask.id = 'aiBatchMask';
	mask.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99998;';
	document.body.appendChild(mask);

	var panel = document.createElement('div');
	panel.id = 'aiBatchPanel';
	panel.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:560px;max-width:90vw;background:#fff;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,0.35);z-index:99999;font-family:微软雅黑,sans-serif;font-size:13px;overflow:hidden;';
	panel.innerHTML =
		'<div style="background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;padding:14px 20px;font-size:15px;font-weight:bold;">🤖 批量 AI 处理进度</div>'
		+'<div style="padding:20px;">'
		+'<div style="margin-bottom:14px;font-size:12px;color:#888;">加载中...</div>'
		+'<div id="aiBatchCurrent" style="background:#f9f9f9;border:1px solid #eee;padding:10px 12px;border-radius:4px;margin-bottom:14px;min-height:40px;color:#555;">准备开始...</div>'
		+'<div style="margin-bottom:6px;display:flex;justify-content:space-between;font-size:12px;color:#555;">'
		+'<span id="aiBatchProgressText">0 / 0</span>'
		+'<span id="aiBatchPercent">0%</span>'
		+'</div>'
		+'<div style="background:#eee;border-radius:10px;height:14px;overflow:hidden;margin-bottom:14px;">'
		+'<div id="aiBatchBar" style="height:100%;width:0%;background:linear-gradient(90deg,#4f46e5,#7c3aed);transition:width .2s ease;"></div>'
		+'</div>'
		+'<div style="display:flex;gap:6px;font-size:12px;margin-bottom:14px;">'
		+'<span>✅ 成功: <b id="aiBatchOk" style="color:#16a34a;">0</b></span>'
		+'<span>❌ 失败: <b id="aiBatchErr" style="color:#dc2626;">0</b></span>'
		+'<span>⏭️ 跳过: <b id="aiBatchSkip" style="color:#888;">0</b></span>'
		+'</div>'
		+'<div style="text-align:right;display:flex;gap:8px;justify-content:flex-end;">'
		+'<input type="button" id="aiBatchPauseBtn" value="⏸️ 暂停" class="btn" style="background:#f59e0b;border-color:#d97706;color:#fff;padding:6px 14px;" onclick="toggleAiBatchPause()" disabled>'
		+'<input type="button" id="aiBatchAbortBtn" value="⏹️ 中断" class="btn" style="background:#ef4444;border-color:#dc2626;color:#fff;padding:6px 14px;" onclick="abortAiBatch()" disabled>'
		+'</div>'
		+'</div>';
	document.body.appendChild(panel);
}

function updateAiBatchUI()
{
	var b = _aiBatch;
	var percent = b.total > 0 ? Math.floor(b.idx * 100 / b.total) : 0;
	var bar = document.getElementById('aiBatchBar');
	var pct = document.getElementById('aiBatchPercent');
	var ptxt = document.getElementById('aiBatchProgressText');
	bar.style.width = percent + '%';
	pct.innerHTML = percent + '%';
	ptxt.innerHTML = b.idx + ' / ' + b.total;
	document.getElementById('aiBatchOk').innerHTML = b.okCount;
	document.getElementById('aiBatchErr').innerHTML = b.errCount;
	document.getElementById('aiBatchSkip').innerHTML = b.skipCount;
}

function setAiBatchCurrent(html){ document.getElementById('aiBatchCurrent').innerHTML = html; }
function setAiBatchButtonsEnabled(enabled){
	document.getElementById('aiBatchPauseBtn').disabled = !enabled;
	document.getElementById('aiBatchAbortBtn').disabled = !enabled;
}

async function fetchAiBatchList()
{
	try{
		setAiBatchCurrent('<span style="color:#888;">📋 获取待处理视频列表...</span>');
		var resp = await fetch('admin_ai.php?action=batch_list');
		var data = await resp.json();
		if(!data.ok){
			closeAiBatchOverlay();
			alert(data.error || '获取列表失败');
			return;
		}
		_aiBatch.list = data.list;
		_aiBatch.total = data.total;
		if(_aiBatch.total === 0){
			setAiBatchCurrent('<span style="color:#16a34a;">🎉 没有需要处理的视频！</span>');
			await new Promise(function(r){ setTimeout(r, 1500); });
			closeAiBatchOverlay();
			return;
		}
		setAiBatchButtonsEnabled(true);
		updateAiBatchUI();
		await runAiBatchLoop();
	}catch(e){
		closeAiBatchOverlay();
		alert('获取列表出错: ' + e.message);
	}
}

async function runAiBatchLoop()
{
	var b = _aiBatch;
	setAiBatchCurrent('<span style="color:#888;">开始处理，共 ' + b.total + ' 条...</span>');

	for(b.idx = 0; b.idx < b.total; b.idx++)
	{
		if(b.abort) break;

		// 暂停等待
		while(b.paused && !b.abort)
		{
			setAiBatchCurrent('<span style="color:#f59e0b;">⏸️ 已暂停 - ' + b.idx + ' / ' + b.total + '</span>');
			await new Promise(function(r){ setTimeout(r, 300); });
		}
		if(b.abort) break;

		var item = b.list[b.idx];
		setAiBatchCurrent('<span style="color:#4f46e5;">⚙️ AI 处理中...</span> <span style="color:#e67e22;font-weight:bold;">#'+item.v_id+'</span> <span style="color:#333;">'+item.v_name+'</span>');

		try{
			var fd = new FormData();
			fd.append('v_id', item.v_id);
			var resp = await fetch('admin_ai.php?action=batch_process', { method:'POST', body:fd });
			var r = await resp.json();
			if(r.ok){
				if(r.skipped){
					b.skipCount++;
					setAiBatchCurrent('<span style="color:#888;">⏭️ #'+item.v_id+' '+item.v_name+' — 已处理过，跳过</span>');
				}else{
					b.okCount++;
					setAiBatchCurrent('<span style="color:#16a34a;">✅ #'+item.v_id+' '+item.v_name+' — 成功</span> <span style="color:#888;font-size:12px;">(导演: '+(r.directors||'-')+')</span>');
				}
			}else{
				b.errCount++;
				b.errList.push('#'+item.v_id+' '+item.v_name+': '+(r.error||'未知错误'));
				setAiBatchCurrent('<span style="color:#dc2626;">❌ #'+item.v_id+' '+item.v_name+' — '+(r.error||'失败')+'</span>');
			}
		}catch(e){
			b.errCount++;
			b.errList.push('#'+item.v_id+' '+item.v_name+': '+e.message);
			setAiBatchCurrent('<span style="color:#dc2626;">❌ #'+item.v_id+' '+item.v_name+' — 网络错误: '+e.message+'</span>');
		}

		updateAiBatchUI();
		await new Promise(function(r){ setTimeout(r, 500); });
	}
	finishAiBatch();
}

function finishAiBatch()
{
	var b = _aiBatch;
	setAiBatchButtonsEnabled(false);
	var abortMsg = b.abort ? '<span style="color:#f59e0b;font-weight:bold;">⏹️ 已中断</span><br><br>' : '';
	var html = abortMsg
		+'<div style="font-size:14px;font-weight:bold;margin-bottom:10px;">📊 处理完成</div>'
		+'✅ 成功: <b style="color:#16a34a;">'+b.okCount+'</b> 条<br>'
		+'❌ 失败: <b style="color:#dc2626;">'+b.errCount+'</b> 条<br>'
		+'⏭️ 跳过: <b>'+b.skipCount+'</b> 条';
	if(b.errList.length > 0){
		html += '<div style="margin-top:10px;font-size:12px;color:#dc2626;"><b>失败列表（重新运行按钮可重试）:</b><br>';
		html += b.errList.map(function(e){ return '• '+e; }).join('<br>');
		html += '</div>';
	}
	setAiBatchCurrent(html);
	var pauseBtn = document.getElementById('aiBatchPauseBtn');
	var abortBtn = document.getElementById('aiBatchAbortBtn');
	if(pauseBtn){ pauseBtn.style.display='none'; }
	if(abortBtn){
		abortBtn.value = '关闭';
		abortBtn.disabled = false;
		abortBtn.onclick = closeAiBatchOverlay;
		abortBtn.style.background='#6b7280';
		abortBtn.style.borderColor='#4b5563';
		abortBtn.style.color='#fff';
	}
}

function toggleAiBatchPause()
{
	if(!_aiBatch) return;
	_aiBatch.paused = !_aiBatch.paused;
	var btn = document.getElementById('aiBatchPauseBtn');
	if(_aiBatch.paused){ btn.value='▶️ 继续'; btn.style.background='#16a34a'; btn.style.borderColor='#15803d'; }
	else{ btn.value='⏸️ 暂停'; btn.style.background='#f59e0b'; btn.style.borderColor='#d97706'; }
}

function abortAiBatch()
{
	if(!_aiBatch) return;
	if(!confirm('确定中断？已成功处理的视频已标记，下次运行会自动跳过。')) return;
	_aiBatch.abort = true;
	_aiBatch.paused = false;
}

function closeAiBatchOverlay()
{
	var m = document.getElementById('aiBatchMask'); if(m) m.remove();
	var p = document.getElementById('aiBatchPanel'); if(p) p.remove();
	_aiBatch = null;
	// 刷新待处理数量显示
	setTimeout(function(){ location.reload(); }, 200);
}

function resetAllAiBatch(total)
{
	if(total === 0){
		alert('数据库中没有视频数据！');
		return;
	}
	if(!confirm('⚠️ 确认重新处理全部 '+total+' 条视频？\n\n所有已处理过的数据会被覆盖，约需 '+Math.ceil(total*1.5/60)+' 分钟，消耗 '+total+' 次 AI 请求的 token 额度。')) return;

	// 先调后端把所有 v_ai 重置为 0，然后静默开始批量处理
	fetch('admin_ai.php?action=batch_reset', { method:'POST' })
		.then(function(r){ return r.json(); })
		.then(function(data){
			if(!data.ok){ alert('重置失败: '+(data.error||'未知错误')); return; }
			runAiBatch();
		})
		.catch(function(e){ alert('重置请求出错: '+e.message); });
}
</script>

<script type="text/javascript">
function aiProviderChange()
{
    var presets = {
        'deepseek':   { url: 'https://api.deepseek.com/v1',                          model: 'deepseek-chat' },
        'openai':     { url: 'https://api.openai.com/v1',                            model: 'gpt-4o-mini' },
        'qwen':       { url: 'https://dashscope.aliyuncs.com/compatible-mode/v1',   model: 'qwen-turbo' },
        'moonshot':   { url: 'https://api.moonshot.cn/v1',                           model: 'moonshot-v1-8k' },
        'volcengine': { url: 'https://ark.cn-beijing.volces.com/api/v3',             model: '' },
        'custom':     { url: '',  model: '' }
    };
    var val = document.getElementById('ai_provider').value;
    if(presets[val])
    {
        if(val !== 'custom' && confirm('切换提供商将自动填入 Base URL 和 Model，是否继续？'))
        {
            document.getElementById('ai_api_url').value = presets[val].url;
            if(presets[val].model) document.getElementById('ai_model').value = presets[val].model;
        }
    }
}
</script>

</div>
  </div>
</div>
<?php
viewFoot();
?>
</body>
</html>
