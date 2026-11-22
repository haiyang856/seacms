<?php
/**
 * 在线升级（基于 GitHub 仓库）
 * 功能：版本检测 / 线路回退下载 / 文件权限修正 / 后台目录映射 / 数据库结构增量升级 / 缓存清理
 */
@set_time_limit(0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
@ini_set('display_errors', '0');

require_once(dirname(__FILE__).'/config.php');
CheckPurview('');

/* ================= 基础配置 ================= */
$updAdminDir   = basename(str_replace('\\', '/', dirname(__FILE__)));
$updRepo       = 'haiyang856/seacms';
$updBranch     = 'main';
$updOfficialZip = 'https://github.com/'.$updRepo.'/archive/refs/heads/'.$updBranch.'.zip';
$updRawPath    = 'data/admin/ver.json';
$updOfficialRaw = 'https://raw.githubusercontent.com/'.$updRepo.'/'.$updBranch.'/'.$updRawPath;
$updMirrors    = array('https://ghproxy.net/', 'https://gh-proxy.com/', 'https://ghfast.top/');
$updDir        = sea_ROOT.'/data/update';
$updZip        = $updDir.'/update.zip';
$updPkg        = $updDir.'/pkg';
$updProgress   = $updDir.'/progress.json';
$updVerFile    = sea_ROOT.'/data/admin/ver.json';
$updCollate    = 'utf8mb4_0900_ai_ci';

/* ================= 通用工具 ================= */
function upd_json($data)
{
	@header('Content-Type: application/json; charset=utf-8');
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit();
}

function upd_csrf_ok()
{
	$token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : (isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '');
	return !empty($_SESSION['admin_csrf_token']) && is_string($token) && $token === $_SESSION['admin_csrf_token'];
}

function upd_size_text($bytes)
{
	if ($bytes >= 1073741824) return round($bytes / 1073741824, 2).' GB';
	if ($bytes >= 1048576) return round($bytes / 1048576, 2).' MB';
	if ($bytes >= 1024) return round($bytes / 1024, 1).' KB';
	return $bytes.' B';
}

function upd_local_version()
{
	global $updVerFile;
	$def = array('ver' => '', 'time' => 0, 'info' => array());
	if (!is_file($updVerFile)) return $def;
	$data = json_decode(@file_get_contents($updVerFile), true);
	if (!is_array($data)) return $def;
	return array(
		'ver'  => isset($data['ver']) ? (string)$data['ver'] : '',
		'time' => isset($data['time']) ? (int)$data['time'] : 0,
		'info' => (isset($data['info']) && is_array($data['info'])) ? $data['info'] : array()
	);
}

function upd_rmdir($dir)
{
	if (!is_dir($dir)) return true;
	$items = @scandir($dir);
	if (!is_array($items)) return false;
	foreach ($items as $item) {
		if ($item === '.' || $item === '..') continue;
		$path = $dir.'/'.$item;
		if (is_dir($path) && !is_link($path)) upd_rmdir($path);
		else @unlink($path);
	}
	return @rmdir($dir);
}

function upd_ensure_dir($dir)
{
	if (!is_dir($dir)) @mkdir($dir, 0755, true);
	if (!is_dir($dir)) return false;
	if (!is_writable($dir)) {
		@chmod($dir, 0755);
		clearstatcache(true, $dir);
	}
	return is_writable($dir);
}

/* ================= 网络请求 ================= */
function upd_http_get($url, $timeout = 8)
{
	if (!function_exists('curl_init')) return array('ok' => false, 'error' => 'cURL 扩展未安装');
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 5,
		CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
		CURLOPT_TIMEOUT        => $timeout,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_USERAGENT      => 'SeaCMS-Updater/1.0'
	));
	$body = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err  = curl_error($ch);
	curl_close($ch);
	if ($body === false || $code < 200 || $code >= 400) {
		return array('ok' => false, 'code' => $code, 'error' => $err !== '' ? $err : 'HTTP '.$code);
	}
	return array('ok' => true, 'code' => $code, 'body' => $body);
}

function upd_url_sources($official, $mirrors)
{
	$urls = array($official);
	foreach ($mirrors as $m) $urls[] = rtrim($m, '/').'/'.$official;
	return $urls;
}

function upd_fetch_latest()
{
	global $updOfficialRaw, $updMirrors;
	$errors = array();
	foreach (upd_url_sources($updOfficialRaw, $updMirrors) as $url) {
		$r = upd_http_get($url, 8);
		if ($r['ok']) {
			$d = json_decode($r['body'], true);
			if (is_array($d) && isset($d['ver'])) {
				return array(
					'ok'     => true,
					'source' => $url,
					'errors' => $errors,
					'data'   => array(
						'ver'  => (string)$d['ver'],
						'time' => isset($d['time']) ? (int)$d['time'] : 0,
						'info' => (isset($d['info']) && is_array($d['info'])) ? $d['info'] : array()
					)
				);
			}
			$errors[] = $url.'：返回内容不是有效的版本 JSON';
		} else {
			$errors[] = $url.'：'.$r['error'];
		}
	}
	return array('ok' => false, 'errors' => $errors);
}

function upd_probe_zip()
{
	global $updOfficialZip, $updMirrors;
	$errors = array();
	foreach (upd_url_sources($updOfficialZip, $updMirrors) as $url) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_NOBODY         => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 5,
			CURLOPT_CONNECTTIMEOUT => 6,
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_USERAGENT      => 'SeaCMS-Updater/1.0'
		));
		curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$len  = (int)curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
		$err  = curl_error($ch);
		curl_close($ch);
		if ($code >= 200 && $code < 400 && ($len <= 0 || $len > 10240)) {
			return array('ok' => true, 'url' => $url, 'size' => $len, 'official' => ($url === $updOfficialZip));
		}
		$errors[] = $url.'：'.($err !== '' ? $err : 'HTTP '.$code);
	}
	return array('ok' => false, 'errors' => $errors);
}

function upd_download_to($url, $dest)
{
	$fp = @fopen($dest, 'wb');
	if (!$fp) return array('ok' => false, 'error' => '无法写入升级包文件，请检查 data/update 目录权限');
	$last = 0;
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_FILE           => $fp,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 5,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT        => 600,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_USERAGENT      => 'SeaCMS-Updater/1.0',
		CURLOPT_NOPROGRESS     => false,
		CURLOPT_PROGRESSFUNCTION => function ($ch, $dt, $dn, $ut, $un) use (&$last, $url) {
			$now = microtime(true);
			if ($now - $last < 0.4 && ($dt <= 0 || $dn < $dt)) return 0;
			$last = $now;
			$percent = $dt > 0 ? round($dn * 100 / $dt, 1) : 0;
			@file_put_contents($GLOBALS['updProgress'], json_encode(array(
				'status'   => 'downloading',
				'url'      => $url,
				'download' => $dn,
				'total'    => $dt,
				'percent'  => $percent
			), JSON_UNESCAPED_UNICODE));
			return 0;
		}
	));
	curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err  = curl_error($ch);
	curl_close($ch);
	fclose($fp);
	if ($code < 200 || $code >= 400 || $err !== '') {
		@unlink($dest);
		return array('ok' => false, 'error' => $err !== '' ? $err : 'HTTP '.$code);
	}
	if (!is_file($dest) || filesize($dest) < 1024) {
		@unlink($dest);
		return array('ok' => false, 'error' => '下载文件过小，可能不是有效的升级包');
	}
	return array('ok' => true);
}

/* ================= 动作：版本检测 ================= */
function upd_action_check()
{
	$local  = upd_local_version();
	$latest = upd_fetch_latest();
	$probe  = upd_probe_zip();
	$local['time_text'] = $local['time'] > 0 ? date('Y-m-d H:i:s', $local['time']) : '';
	if (!$latest['ok']) {
		upd_json(array(
			'ok'      => false,
			'message' => '无法获取 GitHub 最新版本信息',
			'local'   => $local,
			'network' => $probe,
			'errors'  => $latest['errors']
		));
	}
	$latestData = $latest['data'];
	$latestData['time_text'] = $latestData['time'] > 0 ? date('Y-m-d H:i:s', $latestData['time']) : '';
	$hasUpdate = ($latestData['time'] > $local['time']) || ($latestData['ver'] !== $local['ver']);
	upd_json(array(
		'ok'        => true,
		'local'     => $local,
		'latest'    => $latestData,
		'hasUpdate' => $hasUpdate,
		'source'    => $latest['source'],
		'network'   => $probe,
		'errors'    => $latest['errors']
	));
}

/* ================= 动作：下载升级包 ================= */
function upd_action_download()
{
	global $updZip, $updDir, $updProgress, $updOfficialZip, $updMirrors;
	if (!upd_csrf_ok()) upd_json(array('ok' => false, 'message' => 'CSRF 校验失败，请刷新页面重试'));
	@set_time_limit(0);
	if (!upd_ensure_dir($updDir)) upd_json(array('ok' => false, 'message' => 'data/update 目录不可写'));
	@file_put_contents($updDir.'/.htaccess', "Require all denied\nDeny from all\n");
	@unlink($updProgress);

	$sources = upd_url_sources($updOfficialZip, $updMirrors);
	$errors  = array();
	for ($attempt = 1; $attempt <= 3; $attempt++) {
		$url = $sources[($attempt - 1) % count($sources)];
		@file_put_contents($updProgress, json_encode(array(
			'status'   => 'downloading',
			'url'      => $url,
			'percent'  => 0,
			'download' => 0,
			'total'    => 0,
			'attempt'  => $attempt
		), JSON_UNESCAPED_UNICODE));
		$res = upd_download_to($url, $updZip);
		if ($res['ok']) {
			$zip = new ZipArchive();
			$opened = ($zip->open($updZip) === true);
			$num = $opened ? $zip->numFiles : 0;
			if ($opened) $zip->close();
			if ($opened && $num > 0) {
				$size = filesize($updZip);
				@file_put_contents($updProgress, json_encode(array(
					'status' => 'done', 'percent' => 100, 'download' => $size, 'total' => $size, 'url' => $url
				), JSON_UNESCAPED_UNICODE));
				upd_json(array('ok' => true, 'source' => $url, 'size' => $size, 'size_text' => upd_size_text($size)));
			}
			@unlink($updZip);
			$res = array('ok' => false, 'error' => '下载内容不是有效的 ZIP 升级包');
		}
		$errors[] = '第 '.$attempt.' 次（'.$url.'）：'.$res['error'];
	}
	@file_put_contents($updProgress, json_encode(array('status' => 'error', 'percent' => 0, 'message' => '连续 3 次下载失败'), JSON_UNESCAPED_UNICODE));
	upd_json(array(
		'ok'       => false,
		'message'  => '连续 3 次下载失败，请手动下载升级包',
		'errors'   => $errors,
		'official' => $updOfficialZip,
		'mirrors'  => upd_url_sources($updOfficialZip, $updMirrors),
		'manual'   => 'data/update/update.zip'
	));
}

/* ================= 动作：本地上传升级包 ================= */
function upd_action_upload()
{
	global $updZip, $updDir, $updProgress;
	if (!upd_csrf_ok()) upd_json(array('ok' => false, 'message' => 'CSRF 校验失败，请刷新页面重试'));
	if (!upd_ensure_dir($updDir)) upd_json(array('ok' => false, 'message' => 'data/update 目录不可写'));
	if (!isset($_FILES['file']) || !is_array($_FILES['file'])) upd_json(array('ok' => false, 'message' => '未接收到上传文件'));
	$f = $_FILES['file'];
	if (!empty($f['error'])) {
		$map = array(
			UPLOAD_ERR_INI_SIZE   => '文件超过服务器 upload_max_filesize 限制（'.ini_get('upload_max_filesize').'）',
			UPLOAD_ERR_FORM_SIZE  => '文件超过表单允许大小',
			UPLOAD_ERR_PARTIAL    => '文件仅上传了一部分，请重试',
			UPLOAD_ERR_NO_FILE    => '未选择文件',
			UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
			UPLOAD_ERR_CANT_WRITE => '服务器临时目录不可写',
			UPLOAD_ERR_EXTENSION  => '上传被 PHP 扩展拦截'
		);
		upd_json(array('ok' => false, 'message' => isset($map[$f['error']]) ? $map[$f['error']] : '上传失败（错误码 '.$f['error'].'）'));
	}
	if (!is_uploaded_file($f['tmp_name'])) upd_json(array('ok' => false, 'message' => '上传文件校验失败'));
	$size = @filesize($f['tmp_name']);
	if ($size <= 0) upd_json(array('ok' => false, 'message' => '上传文件为空'));
	$zip = new ZipArchive();
	if ($zip->open($f['tmp_name']) !== true) upd_json(array('ok' => false, 'message' => '文件不是有效的 ZIP 升级包'));
	$hasSql = false;
	for ($i = 0; $i < $zip->numFiles; $i++) {
		$name = str_replace('\\', '/', $zip->getNameIndex($i));
		if (preg_match('#(^|/)install/seacms\.sql$#i', $name)) { $hasSql = true; break; }
	}
	$zip->close();
	if (!$hasSql) upd_json(array('ok' => false, 'message' => '升级包中未找到 install/seacms.sql，请确认下载的是完整升级包'));
	if (!@move_uploaded_file($f['tmp_name'], $updZip)) upd_json(array('ok' => false, 'message' => '升级包保存失败，请检查 data/update 目录权限'));
	@file_put_contents($updProgress, json_encode(array('status' => 'done', 'percent' => 100, 'download' => $size, 'total' => $size, 'url' => '本地上传'), JSON_UNESCAPED_UNICODE));
	upd_json(array('ok' => true, 'size' => $size, 'size_text' => upd_size_text($size)));
}

/* ================= 动作：进度查询 ================= */
function upd_action_progress()
{
	global $updProgress;
	$data = array('status' => 'idle', 'percent' => 0, 'download' => 0, 'total' => 0);
	if (is_file($updProgress)) {
		$tmp = json_decode(@file_get_contents($updProgress), true);
		if (is_array($tmp)) $data = $tmp;
	}
	upd_json($data);
}

/* ================= 升级流：NDJSON 输出 ================= */
function upd_emit($status, $msg, $extra = array())
{
	echo json_encode(array_merge(array('status' => $status, 'msg' => $msg, 't' => date('H:i:s')), $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
	@ob_flush();
	@flush();
}

/* ================= 文件规则 ================= */
function upd_should_skip($rel)
{
	$rel = ltrim(str_replace('\\', '/', $rel), '/');
	if ($rel === '') return true;
	if (preg_match('#(^|/)(\.git|\.github|\.workbuddy)(/|$)#', $rel)) return true;
	if (strpos($rel, '.DS_Store') !== false) return true;
	if (preg_match('#^templets/default(/|$)#i', $rel)) return true;
	if ($rel === 'install/install_lock.txt') return true;
	return false;
}

function upd_map_rel($rel)
{
	global $updAdminDir;
	$rel = ltrim(str_replace('\\', '/', $rel), '/');
	if (preg_match('#^admin/#', $rel)) return $updAdminDir.'/'.substr($rel, 6);
	if (preg_match('#^zzz/#', $rel)) return $updAdminDir.'/'.substr($rel, 4);
	return $rel;
}

/* ================= 数据库结构升级 ================= */
function upd_db_connect()
{
	global $cfg_dbhost, $cfg_dbuser, $cfg_dbpwd, $cfg_dbname, $cfg_dbport;
	if (!class_exists('mysqli')) return null;
	mysqli_report(MYSQLI_REPORT_OFF);
	$port = (int)($cfg_dbport ? $cfg_dbport : 3306);
	$db = @new mysqli($cfg_dbhost, $cfg_dbuser, $cfg_dbpwd, $cfg_dbname, $port);
	if ($db->connect_errno) return null;
	@$db->set_charset('utf8mb4');
	return $db;
}

function upd_split_defs($body)
{
	$out = array();
	$buf = '';
	$depth = 0;
	$quote = null;
	$len = strlen($body);
	for ($i = 0; $i < $len; $i++) {
		$c = $body[$i];
		if ($quote !== null) {
			$buf .= $c;
			if ($c === '\\' && $i + 1 < $len) {
				$buf .= $body[++$i];
			} elseif ($c === $quote) {
				$quote = null;
			}
			continue;
		}
		if ($c === "'" || $c === '"') {
			$quote = $c;
			$buf .= $c;
			continue;
		}
		if ($c === '(') $depth++;
		if ($c === ')') $depth--;
		if ($c === ',' && $depth === 0) {
			$out[] = trim($buf);
			$buf = '';
			continue;
		}
		$buf .= $c;
	}
	if (trim($buf) !== '') $out[] = trim($buf);
	return $out;
}

function upd_parse_schema($sql)
{
	$tables = array();
	if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*(ENGINE=[^;]*);/is', $sql, $m, PREG_SET_ORDER)) {
		foreach ($m as $one) {
			$tables[$one[1]] = array(
				'name'    => $one[1],
				'defs'    => upd_split_defs($one[2]),
				'options' => trim($one[3]),
				'raw'     => $one[0]
			);
		}
	}
	return $tables;
}

function upd_parse_column($def)
{
	if (preg_match('/^`([^`]+)`\s+(.+)$/s', $def, $m)) {
		return array('name' => $m[1], 'def' => trim($m[2]));
	}
	return null;
}

function upd_col_type($def)
{
	if (preg_match('/^([a-zA-Z]+(?:\s*\([^)]*\))?(?:\s+unsigned)?(?:\s+zerofill)?)/i', $def, $m)) {
		return strtolower(preg_replace('/\s+/', ' ', trim($m[1])));
	}
	return '';
}

function upd_normalize_type($type)
{
	$type = strtolower(preg_replace('/\s+/', ' ', trim($type)));
	$type = preg_replace('/\(\s+/', '(', $type);
	$type = preg_replace('/\s+\)/', ')', $type);
	// MySQL 8.0.19+ 不再保留整数显示宽度，比较前统一去掉，避免每次升级重复 MODIFY
	$type = preg_replace('/^(tinyint|smallint|mediumint|int|integer|bigint)\(\d+\)/', '$1', $type);
	return trim($type);
}

function upd_norm_cols($cols)
{
	$out = array();
	foreach (explode(',', $cols) as $c) {
		$c = trim($c);
		$c = preg_replace('/\(\s*\d+\s*\)\s*$/', '', $c);
		$c = preg_replace('/\s+(ASC|DESC)$/i', '', $c);
		$c = trim($c, "` \t");
		if ($c !== '') $out[] = $c;
	}
	return $out;
}

function upd_parse_index($def)
{
	if (preg_match('/^PRIMARY\s+KEY\s*\((.*)\)$/is', $def, $m)) {
		return array('name' => 'PRIMARY', 'unique' => true, 'kind' => 'PRIMARY', 'cols' => upd_norm_cols($m[1]));
	}
	if (preg_match('/^(UNIQUE|FULLTEXT|SPATIAL)?\s*KEY\s+`([^`]+)`\s*\((.*?)\)(\s+USING\s+\w+)?$/is', $def, $m)) {
		return array(
			'name'   => $m[2],
			'unique' => (strtoupper($m[1]) === 'UNIQUE'),
			'kind'   => strtoupper($m[1]),
			'cols'   => upd_norm_cols($m[3])
		);
	}
	return null;
}

function upd_type_family($type)
{
	$type = upd_normalize_type($type);
	$textRank = array('tinytext' => 1, 'text' => 2, 'mediumtext' => 3, 'longtext' => 4);
	if (isset($textRank[$type])) return array('text', $textRank[$type]);
	if (preg_match('/^(var)?char\((\d+)\)/', $type, $m)) {
		return array($m[1] === 'var' ? 'varchar' : 'char', (int)$m[2]);
	}
	$base = strtolower(preg_replace('/[\s(].*$/', '', $type));
	$intRank = array('tinyint' => 1, 'smallint' => 2, 'mediumint' => 3, 'int' => 4, 'integer' => 4, 'bigint' => 5);
	if (isset($intRank[$base])) return array('int', $intRank[$base]);
	return null;
}

function upd_is_downgrade($oldType, $newType)
{
	$a = upd_type_family($oldType);
	$b = upd_type_family($newType);
	if (!$a || !$b || $a[0] !== $b[0]) return false;
	return $b[1] < $a[1];
}

function upd_apply_schema($sql)
{
	$schema = upd_parse_schema($sql);
	if (empty($schema)) return array(null, '未能从 install/seacms.sql 解析出建表语句');
	$db = upd_db_connect();
	if (!$db) return array(null, '数据库连接失败，请检查 data/common.inc.php');

	$stats = array('created' => 0, 'altered' => 0, 'cols_add' => 0, 'cols_mod' => 0, 'idx_add' => 0, 'skipped' => 0, 'optimized' => 0, 'errors' => array());
	$existing = array();
	$res = $db->query("SELECT TABLE_NAME,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()");
	if ($res) {
		while ($row = $res->fetch_assoc()) $existing[$row['TABLE_NAME']] = $row;
	}

	foreach ($schema as $tableName => $table) {
		$tn = $db->real_escape_string($tableName);
		$createSql = preg_replace('/ENGINE=\w+/i', 'ENGINE=InnoDB', $table['raw']);
		$createSql = preg_replace('/DEFAULT\s+CHARSET=\w+(\s+COLLATE=\w+)?/i', 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci', $createSql);

		if (!isset($existing[$tableName])) {
			if ($db->query($createSql)) {
				$stats['created']++;
				upd_emit('success', '已创建新表 '.$tableName);
			} else {
				$stats['errors'][] = $tableName.' 建表失败：'.$db->error;
				upd_emit('error', $tableName.' 建表失败：'.$db->error);
			}
			continue;
		}

		$clauses = array();
		$changed = false;

		/* 列对比 */
		$oldCols = array();
		$r = $db->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$tn."' ORDER BY ORDINAL_POSITION");
		if ($r) {
			while ($c = $r->fetch_assoc()) $oldCols[strtolower($c['COLUMN_NAME'])] = $c;
		}
		$prev = null;
		foreach ($table['defs'] as $def) {
			$col = upd_parse_column($def);
			if (!$col) continue;
			$name = $col['name'];
			$key = strtolower($name);
			if (!isset($oldCols[$key])) {
				$clause = 'ADD COLUMN `'.$name.'` '.$col['def'].($prev !== null ? ' AFTER `'.$prev.'`' : ' FIRST');
				$clauses[] = $clause;
				$stats['cols_add']++;
				$changed = true;
			} else {
				$oc = $oldCols[$key];
				$newType = upd_normalize_type(upd_col_type($col['def']));
				$oldType = upd_normalize_type($oc['COLUMN_TYPE']);
				$newNull = preg_match('/\bNOT\s+NULL\b/i', $col['def']) ? 'NO' : 'YES';
				$newAuto = stripos($col['def'], 'auto_increment') !== false;
				$oldAuto = stripos($oc['EXTRA'], 'auto_increment') !== false;
				$typeDiff = ($newType !== $oldType);
				if ($typeDiff && upd_is_downgrade($oldType, $newType)) {
					$stats['skipped']++;
					$prev = $name;
					continue;
				}
				if ($typeDiff || $newNull !== $oc['IS_NULLABLE'] || $newAuto !== $oldAuto) {
					$clauses[] = 'MODIFY COLUMN `'.$name.'` '.$col['def'];
					$stats['cols_mod']++;
					$changed = true;
				}
			}
			$prev = $name;
		}

		/* 索引对比 */
		$oldIdx = array();
		$r = $db->query("SELECT INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$tn."' GROUP BY INDEX_NAME");
		if ($r) {
			while ($i = $r->fetch_assoc()) $oldIdx[strtoupper($i['INDEX_NAME'])] = strtolower($i['cols']);
		}
		foreach ($table['defs'] as $def) {
			$idx = upd_parse_index($def);
			if (!$idx) continue;
			$idxKey = strtoupper($idx['name']);
			$cols = '`'.implode('`,`', $idx['cols']).'`';
			$newCols = strtolower(implode(',', $idx['cols']));
			if (!isset($oldIdx[$idxKey])) {
				if ($idx['name'] === 'PRIMARY') {
					$clauses[] = 'ADD PRIMARY KEY ('.$cols.')';
				} elseif ($idx['kind'] === 'FULLTEXT' || $idx['kind'] === 'SPATIAL') {
					$clauses[] = 'ADD '.$idx['kind'].' KEY `'.$idx['name'].'` ('.$cols.')';
				} else {
					$clauses[] = 'ADD '.($idx['unique'] ? 'UNIQUE ' : '').'KEY `'.$idx['name'].'` ('.$cols.')';
				}
				$stats['idx_add']++;
				$changed = true;
			} elseif ($oldIdx[$idxKey] !== $newCols) {
				$stats['skipped']++;
			}
		}

		/* 引擎与字符集 */
		$needConvert = (stripos($existing[$tableName]['TABLE_COLLATION'], 'utf8mb4') !== 0);
		$engineDiff = (strcasecmp($existing[$tableName]['ENGINE'], 'InnoDB') !== 0);
		if ($needConvert || $engineDiff || !empty($clauses)) {
			$clauses[] = $needConvert
				? 'CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci'
				: 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';
		}

		if (!empty($clauses)) {
			$alterSql = 'ALTER TABLE `'.$tableName.'` '.implode(', ', $clauses);
			if ($db->query($alterSql)) {
				$stats['altered']++;
				$changed = true;
				upd_emit('success', '表 '.$tableName.' 结构已更新');
			} else {
				$stats['errors'][] = $tableName.' 升级失败：'.$db->error;
				upd_emit('error', $tableName.' 升级失败：'.$db->error);
			}
		}
		if ($changed) {
			$db->query('OPTIMIZE TABLE `'.$tableName.'`');
			$stats['optimized']++;
		}
	}
	$db->close();
	if ($stats['skipped'] > 0) {
		upd_emit('info', '跳过 '.$stats['skipped'].' 个本地定义更大或不同的列/索引，未做破坏性修改');
	}
	return array($stats, null);
}

/* ================= 缓存清理 ================= */
function upd_clear_cache()
{
	$count = 0;
	$fail = 0;
	$cacheDir = sea_ROOT.'/data/cache';
	if (is_dir($cacheDir)) {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($it as $file) {
			if ($file->isFile()) {
				if (@unlink($file->getPathname())) $count++;
				else $fail++;
			}
		}
	}
	if (function_exists('opcache_reset')) @opcache_reset();
	return array($count, $fail);
}

/* ================= 动作：执行升级（NDJSON 流） ================= */
function upd_action_upgrade()
{
	global $updZip, $updPkg, $updDir, $updProgress, $updAdminDir, $updVerFile;

	@header('Content-Type: application/x-ndjson; charset=utf-8');
	@header('X-Accel-Buffering: no');
	while (ob_get_level() > 0) { @ob_end_flush(); }

	register_shutdown_function(function () {
		$e = error_get_last();
		if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
			upd_emit('error', '升级异常终止：'.$e['message']);
		}
	});

	if (!upd_csrf_ok()) {
		upd_emit('error', 'CSRF 校验失败，请刷新页面重试');
		exit();
	}
	if (!is_file($updZip)) {
		upd_emit('error', '未找到升级包，请先下载或手动上传至 data/update/update.zip');
		exit();
	}

	$zip = new ZipArchive();
	if ($zip->open($updZip) !== true) {
		upd_emit('error', '升级包无法打开或已损坏');
		exit();
	}

	/* 1. 解压 */
	upd_emit('info', '开始解压升级包...');
	upd_rmdir($updPkg);
	if (!upd_ensure_dir($updPkg)) {
		upd_emit('error', '无法创建解压目录 data/update/pkg');
		exit();
	}
	if (!$zip->extractTo($updPkg)) {
		$zip->close();
		upd_emit('error', '解压失败，请检查磁盘空间与目录权限');
		exit();
	}
	$zip->close();
	$root = null;
	if (is_file($updPkg.'/install/seacms.sql')) {
		$root = $updPkg;
	} else {
		$dirs = glob($updPkg.'/*', GLOB_ONLYDIR);
		if (is_array($dirs)) {
			foreach ($dirs as $d) {
				if (is_file($d.'/install/seacms.sql')) { $root = $d; break; }
			}
		}
	}
	if ($root === null) {
		upd_emit('error', '升级包结构异常：未找到 install/seacms.sql');
		exit();
	}
	upd_emit('success', '解压完成');

	/* 1.5 升级包版本一致性校验 */
	$pkgVerFile = $root.'/data/admin/ver.json';
	$pkgTime = 0;
	if (is_file($pkgVerFile)) {
		$pkgData = json_decode(@file_get_contents($pkgVerFile), true);
		if (is_array($pkgData) && isset($pkgData['time'])) $pkgTime = (int)$pkgData['time'];
	}
	$localVer = upd_local_version();
	if ($pkgTime > 0 && $pkgTime === (int)$localVer['time']) {
		upd_rmdir($updPkg);
		upd_emit('success', '升级包版本和当前版本一致，无需升级。');
		exit();
	}

	/* 2. 递归遍历，权限校验与修正 */
	upd_emit('info', '开始递归校验文件权限...');
	$fileMap = array();
	$skipped = 0;
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ($it as $file) {
		$rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
		$isFile = $file->isFile();
		if (upd_should_skip($rel)) { if ($isFile) $skipped++; continue; }
		$dest = sea_ROOT.'/'.upd_map_rel($rel);
		/* data 目录及其子目录只新增本地不存在的内容，已有文件/目录不修改不替换 */
		if (strpos($rel, 'data/') === 0) {
			if (file_exists($dest)) { if ($isFile) $skipped++; continue; }
			if (!$isFile) { @mkdir($dest, 0755, true); continue; }
			$fileMap[$rel] = $dest;
			continue;
		}
		if (!$isFile) continue;
		$fileMap[$rel] = $dest;
	}
	$fixed = 0;
	$broken = array();
	$checkedDirs = array();
	foreach ($fileMap as $rel => $dest) {
		$dir = dirname($dest);
		if (!isset($checkedDirs[$dir])) {
			$checkedDirs[$dir] = true;
			if (!upd_ensure_dir($dir)) {
				$broken[] = $dir.'（目录不可写）';
				continue;
			}
		}
		if (is_file($dest) && !is_writable($dest)) {
			@chmod($dest, 0664);
			clearstatcache(true, $dest);
			if (is_writable($dest)) $fixed++;
			else $broken[] = $dest;
		}
	}
	if (!empty($broken)) {
		$shown = array_slice($broken, 0, 8);
		upd_emit('error', '存在无法修正权限的文件，已中止升级：');
		foreach ($shown as $b) upd_emit('error', $b);
		if (count($broken) > count($shown)) upd_emit('error', '……其余 '. (count($broken) - count($shown)) .' 项未显示');
		exit();
	}
	upd_emit('success', '权限校验完成'.($fixed > 0 ? '，自动修正 '.$fixed.' 个文件权限' : '，无需修正'));

	/* 3. 覆盖文件（templets/default 已排除，admin 目录已映射） */
	upd_emit('info', '开始覆盖升级文件（templets/default 已排除，data 目录仅新增不覆盖，后台目录映射为 '.$updAdminDir.'）...');
	$copied = 0;
	$failed = 0;
	$failFiles = array();
	foreach ($fileMap as $rel => $dest) {
		$dir = dirname($dest);
		if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $failed++; $failFiles[] = $rel; continue; }
		$in = @fopen($root.'/'.$rel, 'rb');
		$out = @fopen($dest, 'wb');
		if (!$in || !$out) {
			if ($in) fclose($in);
			if ($out) fclose($out);
			$failed++;
			$failFiles[] = $rel;
			continue;
		}
		$ok = stream_copy_to_stream($in, $out);
		fclose($in);
		fclose($out);
		if ($ok === false) { $failed++; $failFiles[] = $rel; }
		else $copied++;
	}
	if ($failed > 0) {
		upd_emit('error', '有 '.$failed.' 个文件覆盖失败，升级中止（数据库未改动）：');
		foreach (array_slice($failFiles, 0, 8) as $f) upd_emit('error', $f);
		exit();
	}
	upd_emit('success', '文件覆盖完成：更新 '.$copied.' 个文件，跳过 '.$skipped.' 个（templets/default/已存在的 data 文件）');

	/* 4. 数据库结构升级 */
	upd_emit('info', '开始比对并升级数据库结构...');
	$sqlFile = $root.'/install/seacms.sql';
	$sqlContent = @file_get_contents($sqlFile);
	if ($sqlContent === false || trim($sqlContent) === '') {
		upd_emit('error', '读取升级包内 install/seacms.sql 失败');
		exit();
	}
	list($dbStats, $dbError) = upd_apply_schema($sqlContent);
	if ($dbError !== null) {
		upd_emit('error', $dbError);
		exit();
	}
	upd_emit('success', '数据库升级完成：新建表 '.$dbStats['created'].'，变更表 '.$dbStats['altered'].'，新增列 '.$dbStats['cols_add'].'，修改列 '.$dbStats['cols_mod'].'，新增索引 '.$dbStats['idx_add'].'，优化表 '.$dbStats['optimized']);
	if (!empty($dbStats['errors'])) {
		foreach ($dbStats['errors'] as $e) upd_emit('error', $e);
		upd_emit('error', '数据库升级存在错误，升级包已保留以便排查');
		exit();
	}

	/* 5. 清理缓存 */
	upd_emit('info', '开始清理系统缓存...');
	list($cacheCount, $cacheFail) = upd_clear_cache();
	if ($cacheFail > 0) upd_emit('error', '有 '.$cacheFail.' 个缓存文件清理失败');
	else upd_emit('success', '缓存清理完成，共清理 '.$cacheCount.' 个缓存文件');

	/* 6. 写入版本号 */
	$pkgVer = $root.'/data/admin/ver.json';
	$verWritten = false;
	if (is_file($pkgVer)) {
		$verData = json_decode(@file_get_contents($pkgVer), true);
		if (is_array($verData) && isset($verData['ver'])) {
			$verWritten = (bool)@file_put_contents($updVerFile, json_encode($verData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		}
	}
	if ($verWritten) upd_emit('success', '版本号已更新');
	else upd_emit('error', '版本号写入失败，请检查 data/admin 目录权限');

	/* 7. 清理升级文件 */
	upd_emit('info', '清理升级临时文件...');
	upd_rmdir($updPkg);
	@unlink($updZip);
	@unlink($updProgress);
	if (!is_file($updZip)) upd_emit('success', '升级包已删除');
	else upd_emit('error', '升级包删除失败，请手动删除 data/update/update.zip');

	upd_emit('success', '升级流程全部完成，当前版本 '.($verWritten ? '已同步为最新版本' : '未更新（见上方错误）'));
	exit();
}

/* ================= 动作分发 ================= */
$updAct = isset($_GET['act']) ? $_GET['act'] : '';
if ($updAct === 'check') { upd_action_check(); }
if ($updAct === 'download') { upd_action_download(); }
if ($updAct === 'upload') { upd_action_upload(); }
if ($updAct === 'progress') { upd_action_progress(); }
if ($updAct === 'upgrade') { upd_action_upgrade(); }

/* ================= 页面 ================= */
$updLocal = upd_local_version();
$updLocalTimeText = $updLocal['time'] > 0 ? date('Y-m-d H:i:s', $updLocal['time']) : '未知';
$updCsrf = admin_csrf_token();
$updUploadMaxText = ini_get('upload_max_filesize');
$updMirrorUrls = upd_url_sources($updOfficialZip, $updMirrors);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex,nofollow" />
<title>在线升级</title>
<link href="img/style.css" rel="stylesheet" type="text/css" />
<style>
.upd-card{margin-bottom:14px;}
.upd-row{padding:10px 14px;font-size:13px;line-height:1.9;}
.upd-label{color:#666;display:inline-block;}
.upd-ver{font-weight:bold;color:#1a5fb4;}
.upd-muted{color:#888;}
.upd-log{list-style:none;margin:0;padding:8px 14px;font-family:Menlo,Consolas,monospace;font-size:12px;max-height:320px;overflow:auto;background:#fafafa;border:1px solid #e5e5e5;}
.upd-log li{padding:3px 0;border-bottom:1px dashed #eee;word-break:break-all;}
.upd-success{color:#0a7d32;}
.upd-tip-ok{color:#0a7d32;font-weight:bold;}
.upd-tip-new{color:#d0021b;font-weight:bold;}
.upd-spinner{display:inline-block;width:12px;height:12px;border:2px solid #c9d6e3;border-top-color:#1a73e8;border-radius:50%;margin-right:5px;vertical-align:-2px;animation:upd-spin .7s linear infinite;}
@keyframes upd-spin{to{transform:rotate(360deg);}}
#changelog{line-height:1.55;}
#changelog li{color:#3f8f61;list-style:none;line-height:1.5;margin:1px 0;}
#changelog li::before{content:'';display:inline-block;width:4px;height:4px;border-radius:50%;background:#8ec9a8;vertical-align:middle;margin-right:6px;position:relative;top:-1px;}
.upd-error{color:#c62828;}
.upd-info{color:#555;}
.upd-time{color:#aaa;margin-right:6px;}
.upd-bar{height:22px;background:#eceff3;border-radius:11px;overflow:hidden;margin:6px 0 4px;max-width:680px;}
.upd-bar-inner{height:100%;width:0;background:linear-gradient(90deg,#3b82f6,#22c55e);color:#fff;font-size:12px;text-align:center;line-height:22px;transition:width .25s ease;}
.upd-btn{border:0;background:#1a73e8;color:#fff;padding:8px 20px;border-radius:4px;cursor:pointer;font-size:13px;margin-right:8px;-webkit-tap-highlight-color:transparent;}
.upd-btn:hover,.upd-btn:active,.upd-btn:focus{border:0 !important;box-shadow:none !important;transform:none !important;transition:none !important;outline:0;font-size:13px;padding:8px 20px;}
.upd-btn:disabled{background:#b9c6d8;cursor:not-allowed;}
.upd-btn.gray{background:#6b7280;}
.upd-links{background:#fff8e6;border:1px solid #f0d58c;padding:12px 16px;font-size:12px;line-height:2;}
.upd-links a{color:#1a5fb4;word-break:break-all;}
</style>
</head>
<body>
<script type="text/JavaScript">if(parent.$('admincpnav')) parent.$('admincpnav').innerHTML='后台首页&nbsp;&raquo;&nbsp;工具&nbsp;&raquo;&nbsp;在线升级';</script>
<div class="r_main">
<div class="r_content">
<div class="r_content_1">

<table border="0" cellpadding="0" cellspacing="0" align="center" class="tb upd-card">
<tr class="thead"><th colspan="2">版本信息</th></tr>
<tr><td class="upd-row" colspan="2">
	<span class="upd-label">当前版本：</span><span class="upd-ver" id="localVer"><?php echo htmlspecialchars($updLocal['ver'] !== '' ? $updLocal['ver'] : '未知', ENT_QUOTES, 'utf-8'); ?></span>
	<span class="upd-muted" id="localTime">（<?php echo $updLocalTimeText; ?>）</span>
	&nbsp;&nbsp;<span class="upd-label">最新版本：</span><span class="upd-ver" id="latestVer">检测中...</span>
	<span class="upd-muted" id="latestTime"></span>
	<div id="netInfo" class="upd-muted" style="margin-top:6px;"></div>
</td></tr>
<tr><td class="upd-row">
	<button type="button" class="upd-btn gray" id="btnRecheck">重新检测</button>
	<span id="checkTip" class="upd-muted"></span>
</td></tr>
</table>

<div id="changelogBox" style="display:none;">
<table border="0" cellpadding="0" cellspacing="0" align="center" class="tb upd-card">
<tr class="thead"><th>更新日志</th></tr>
<tr><td class="upd-row"><ul id="changelog" style="margin:0;padding-left:18px;line-height:2;"></ul></td></tr>
<tr><td class="upd-row">
	<button type="button" class="upd-btn" id="btnUpgrade">确认升级</button>
	<span class="upd-muted">升级前请确保 data 目录可写，建议先备份数据库。</span>
</td></tr>
</table>
</div>

<div id="progressWrap" style="display:none;">
<table border="0" cellpadding="0" cellspacing="0" align="center" class="tb upd-card">
<tr class="thead"><th>升级包下载</th></tr>
<tr><td class="upd-row">
	<div class="upd-bar"><div class="upd-bar-inner" id="updBar">0%</div></div>
	<div id="dlInfo" class="upd-muted">等待开始...</div>
</td></tr>
</table>
</div>

<table border="0" cellpadding="0" cellspacing="0" align="center" class="tb upd-card">
<tr class="thead"><th>升级进度</th></tr>
<tr><td class="upd-row" style="padding:0;">
	<ul class="upd-log" id="stepLog"><li class="upd-info">等待操作...</li></ul>
</td></tr>
</table>

<div id="manualBox" class="upd-links" style="display:none;">
	<b id="manualTitle">自动下载失败，请手动下载升级包：</b><br />
	1. 点击下方官方或加速地址下载 ZIP 升级包（也可访问 <a href="https://github.com/<?php echo htmlspecialchars($updRepo, ENT_QUOTES, 'utf-8'); ?>" target="_blank">GitHub 仓库</a> 手动下载）；<br />
	2. 选择已下载的 ZIP 文件，点击「上传并升级」，程序会自动上传并开始升级；<br />
	3. 也可将文件命名为 <b>update.zip</b> 上传到服务器 <b>data/update/</b> 目录，再点击「使用已上传的升级包继续」。<br />
	<span class="upd-muted">单个文件不超过 <?php echo htmlspecialchars($updUploadMaxText, ENT_QUOTES, 'utf-8'); ?>。</span><br /><br />
	官方地址：<a id="manualOfficial" href="#" target="_blank"></a><br />
	<?php foreach ($updMirrorUrls as $i => $u) { if ($i === 0) continue; ?>
	加速地址 <?php echo $i; ?>：<a href="<?php echo htmlspecialchars($u, ENT_QUOTES, 'utf-8'); ?>" target="_blank"><?php echo htmlspecialchars($u, ENT_QUOTES, 'utf-8'); ?></a><br />
	<?php } ?>
	<br /><input type="file" id="manualFile" accept=".zip,application/zip" />
	<button type="button" class="upd-btn" id="btnUpload">上传并升级</button>
	<button type="button" class="upd-btn gray" id="btnManual">使用已上传的升级包继续</button>
	<div id="uploadTip" class="upd-muted" style="margin-top:6px;"></div>
</div>

</div>
</div>
</div>
<?php viewFoot(); ?>
<script type="text/javascript">
var TOKEN = <?php echo json_encode($updCsrf); ?>;
var OFFICIAL = <?php echo json_encode($updOfficialZip, JSON_UNESCAPED_SLASHES); ?>;
var busy = false;

function $(id){ return document.getElementById(id); }
function showManual(title){
	if (title) $('manualTitle').textContent = title;
	$('manualOfficial').textContent = OFFICIAL;
	$('manualOfficial').href = OFFICIAL;
	$('manualBox').style.display = 'block';
}
function addStep(status, msg){
	var li = document.createElement('li');
	li.className = 'upd-' + (status === 'success' ? 'success' : (status === 'error' ? 'error' : 'info'));
	var t = document.createElement('span');
	t.className = 'upd-time';
	t.textContent = new Date().toTimeString().substr(0, 8);
	li.appendChild(t);
	li.appendChild(document.createTextNode((status === 'success' ? '✓ ' : (status === 'error' ? '✗ ' : '· ')) + msg));
	var log = $('stepLog');
	if (log.children.length === 1 && log.children[0].className === 'upd-info') log.innerHTML = '';
	log.appendChild(li);
	log.scrollTop = log.scrollHeight;
}
function fmtSize(n){
	if (!n) return '0 B';
	if (n >= 1048576) return (n / 1048576).toFixed(2) + ' MB';
	if (n >= 1024) return (n / 1024).toFixed(1) + ' KB';
	return n + ' B';
}
function setBusy(v){
	busy = v;
	$('btnUpgrade').disabled = v;
	$('btnRecheck').disabled = v;
	$('btnManual').disabled = v;
}
function renderVersion(j){
	if (j.local) {
		$('localVer').textContent = j.local.ver || '未知';
		if (j.local.time_text) $('localTime').textContent = '（' + j.local.time_text + '）';
	}
	if (j.latest) {
		$('latestVer').textContent = j.latest.ver || '未知';
		$('latestTime').textContent = j.latest.time_text ? '（' + j.latest.time_text + '）' : '';
		var list = $('changelog');
		list.innerHTML = '';
		var info = j.latest.info || [];
		if (!info.length) {
			var li = document.createElement('li');
			li.textContent = '暂无更新说明';
			list.appendChild(li);
		} else {
			for (var i = 0; i < info.length; i++) {
				var li = document.createElement('li');
				li.textContent = String(info[i]);
				list.appendChild(li);
			}
		}
		$('changelogBox').style.display = j.hasUpdate ? 'block' : 'none';
		$('checkTip').textContent = j.hasUpdate ? '发现可用更新' : '当前已是最新版本';
		$('checkTip').className = j.hasUpdate ? 'upd-tip-new' : 'upd-tip-ok';
	} else {
		$('latestVer').textContent = '获取失败';
	}
	if (j.network) {
		if (j.network.ok) {
			$('netInfo').textContent = '升级线路：' + (j.network.official ? 'GitHub 官方可用' : '官方不可用，已探测到可用加速线路');
		} else {
			$('netInfo').textContent = '升级线路：全部不可用，请检查网络或手动下载';
		}
	}
}
function checkVersion(){
	if (busy) return;
	$('checkTip').innerHTML = '<span class="upd-spinner"></span>检测中...';
	$('checkTip').className = 'upd-muted';
	$('latestVer').textContent = '检测中...';
	fetch('?act=check&_=' + Date.now())
		.then(function(r){ return r.json(); })
		.then(function(j){
			renderVersion(j);
			if (!j.ok) {
				$('checkTip').textContent = '检测失败：' + (j.message || '网络异常');
				$('checkTip').className = 'upd-muted';
				$('changelogBox').style.display = 'none';
				showManual('无法获取 GitHub 最新版本信息，请手动下载升级包：');
				addStep('error', '无法获取 GitHub 最新版本信息，可以手动升级。如已完成升级，则无需理会。');
			}
		})
		.catch(function(){ $('checkTip').textContent = '检测失败：网络请求异常'; $('checkTip').className = 'upd-muted'; showManual('网络请求异常，无法获取 GitHub 最新版本信息，请手动下载升级包：'); });
}
function pollProgress(){
	fetch('?act=progress&_=' + Date.now())
		.then(function(r){ return r.json(); })
		.then(function(j){
			var pct = j.status === 'done' ? 100 : (j.percent || 0);
			$('updBar').style.width = pct + '%';
			$('updBar').textContent = pct + '%';
			var txt = '已下载 ' + fmtSize(j.download || 0);
			if (j.total) txt += ' / ' + fmtSize(j.total);
			if (j.url) txt += '　来源：' + j.url;
			if (j.attempt) txt += '　第 ' + j.attempt + ' 次尝试';
			$('dlInfo').textContent = txt;
		})
		.catch(function(){});
}
function doDownload(){
	if (busy) return;
	setBusy(true);
	$('progressWrap').style.display = 'block';
	$('updBar').style.width = '0%';
	$('updBar').textContent = '0%';
	$('dlInfo').textContent = '正在连接下载源...';
	addStep('info', '开始下载升级包，优先使用 GitHub 官方地址...');
	var timer = setInterval(pollProgress, 400);
	pollProgress();
	var fd = new FormData();
	fd.append('csrf_token', TOKEN);
	fetch('?act=download', { method: 'POST', body: fd })
		.then(function(r){ return r.json(); })
		.then(function(j){
			clearInterval(timer);
			if (j.ok) {
				pollProgress();
				addStep('success', '升级包下载完成（' + j.size_text + '，来源：' + j.source + '）');
				runUpgrade();
			} else {
				addStep('error', j.message || '下载失败');
				if (j.errors) for (var i = 0; i < Math.min(j.errors.length, 3); i++) addStep('error', j.errors[i]);
				showManual('自动下载失败，请手动下载升级包：');
				setBusy(false);
			}
		})
		.catch(function(e){
			clearInterval(timer);
			addStep('error', '下载请求异常：' + e);
			showManual('网络异常，自动下载失败，请手动下载升级包：');
			setBusy(false);
		});
}
function handleStep(line){
	var j;
	try { j = JSON.parse(line); } catch (e) { return; }
	if (!j || !j.msg) return;
	addStep(j.status || 'info', j.msg);
}
function runUpgrade(){
	setBusy(true);
	var fd = new FormData();
	fd.append('csrf_token', TOKEN);
	fetch('?act=upgrade', { method: 'POST', body: fd })
		.then(function(resp){
			if (!resp.body || !resp.body.getReader) return resp.text().then(function(t){
				t.split('\n').forEach(handleStep);
			});
			var reader = resp.body.getReader();
			var decoder = new TextDecoder();
			var buf = '';
			function read(){
				return reader.read().then(function(r){
					if (r.done) {
						if (buf.trim() !== '') handleStep(buf.trim());
						return;
					}
					buf += decoder.decode(r.value, { stream: true });
					var idx;
					while ((idx = buf.indexOf('\n')) >= 0) {
						var line = buf.slice(0, idx).trim();
						buf = buf.slice(idx + 1);
						if (line !== '') handleStep(line);
					}
					return read();
				});
			}
			return read();
		})
		.then(function(){
			addStep('info', '流程结束，正在刷新版本信息...');
			setTimeout(function(){ setBusy(false); checkVersion(); }, 600);
		})
		.catch(function(e){
			addStep('error', '升级请求异常：' + e);
			setBusy(false);
		});
}
$('btnRecheck').onclick = checkVersion;
$('btnUpgrade').onclick = doDownload;
$('btnUpload').onclick = function(){
	if (busy) return;
	var inp = $('manualFile');
	if (!inp.files || !inp.files.length) {
		$('uploadTip').className = 'upd-error';
		$('uploadTip').textContent = '请先选择 ZIP 升级包';
		return;
	}
	var f = inp.files[0];
	if (!/\.zip$/i.test(f.name)) {
		$('uploadTip').className = 'upd-error';
		$('uploadTip').textContent = '仅支持 .zip 格式的升级包';
		return;
	}
	setBusy(true);
	$('uploadTip').className = 'upd-muted';
	$('uploadTip').textContent = '正在上传 ' + f.name + '（' + fmtSize(f.size) + '）...';
	addStep('info', '开始上传本地升级包：' + f.name + '（' + fmtSize(f.size) + '）');
	var fd = new FormData();
	fd.append('csrf_token', TOKEN);
	fd.append('file', f);
	fetch('?act=upload', { method: 'POST', body: fd })
		.then(function(r){ return r.json(); })
		.then(function(j){
			if (j.ok) {
				$('uploadTip').className = 'upd-success';
				$('uploadTip').textContent = '上传完成（' + j.size_text + '），开始升级...';
				addStep('success', '升级包上传完成（' + j.size_text + '）');
				runUpgrade();
			} else {
				$('uploadTip').className = 'upd-error';
				$('uploadTip').textContent = j.message || '上传失败';
				addStep('error', j.message || '升级包上传失败');
				setBusy(false);
			}
		})
		.catch(function(e){
			$('uploadTip').className = 'upd-error';
			$('uploadTip').textContent = '上传请求异常：' + e;
			addStep('error', '上传请求异常：' + e);
			setBusy(false);
		});
};
$('btnManual').onclick = function(){
	if (busy) return;
	addStep('info', '尝试使用 data/update/update.zip...');
	runUpgrade();
};
checkVersion();
</script>
</body>
</html>
