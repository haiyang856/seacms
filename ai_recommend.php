<?php
/**
 * 前台 AI 影片推荐接口
 *
 *   GET  ?action=status        返回功能是否开启
 *   POST ?action=chat          访客对话请求推荐
 *        message  访客输入
 *        history  最近对话 JSON（可选）
 */
error_reporting(0);
@header('Content-Type: application/json; charset=utf-8');
@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once(__DIR__.'/include/gpc.filter.php');

function ai_rec_out($arr)
{
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

/** 校验 Origin/Referer 是否与当前站点同源 */
function ai_rec_same_origin()
{
    $source = '';
    if(!empty($_SERVER['HTTP_ORIGIN'])) $source = $_SERVER['HTTP_ORIGIN'];
    elseif(!empty($_SERVER['HTTP_REFERER'])) $source = $_SERVER['HTTP_REFERER'];
    if($source === '') return false;

    $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
    if($host === '') return false;

    $hostName = preg_replace('/:\d+$/', '', $host);
    $srcHost = strtolower((string)parse_url($source, PHP_URL_HOST));
    if($srcHost === '' || $srcHost !== $hostName) return false;

    $srcPort = parse_url($source, PHP_URL_PORT);
    $hostPort = parse_url('http://'.$host, PHP_URL_PORT);
    if($srcPort !== null && $hostPort !== null && intval($srcPort) !== intval($hostPort)) return false;

    return true;
}

/** 轻量读取 AI 配置，供 status 使用（不连接数据库） */
function ai_rec_config()
{
    $file = __DIR__.'/data/admin/ai.php';
    if(!file_exists($file)) $file = __DIR__.'/data/admin/deepseek.php';
    if(!file_exists($file)) return array('open'=>'off', 'recommend'=>'off', 'api_key'=>'');

    $ai_open = 'off';
    $ai_recommend = 'off';
    $ai_api_key = '';
    $deepseek_open = '';
    include $file;

    if($ai_open !== 'on' && $deepseek_open === 'on') $ai_open = 'on';

    return array(
        'open'      => $ai_open,
        'recommend' => $ai_recommend,
        'api_key'   => $ai_api_key,
    );
}

/** 从访客输入中提取检索关键词 */
function ai_rec_terms($message)
{
    $stop = array(
        '推荐几部','推荐点','来几部','来点','我想看','我想','想看','几部','一部','一些',
        '有没有','有什么','什么','好看的','好看','好片','片子','电影','影片','电视剧','剧集',
        '最近','适合','给我','帮我','谢谢','请问','可以','能不能','类似','这种','那种',
        '类型','题材','方面','相关','有关','关于','之类','作品','节目','主题','风格',
        '高分','评分高','口碑','经典','热门','冷门','周末','放松','无聊','一下','有点','点'
    );
    usort($stop, function($a, $b){
        return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
    });

    $clean = str_replace($stop, ' ', $message);
    // 把“的/得/地”当分隔符，避免“热血的动作片”整体变成一个词
    $parts = preg_split('/[\s,，。！？!?、;；:：\'"“”‘’《》()（）\[\]\/\\\\|+~@#$%^&*_=<>·的得地]+/u', $clean);

    $terms = array();
    foreach((array)$parts as $p)
    {
        $p = trim($p);
        if($p === '') continue;

        // 去掉“题材/方面/相关”等无意义尾巴
        $p = preg_replace('/(题材|方面|相关|类型|作品|节目|主题|风格|之类)$/u', '', $p);
        $p = preg_replace('/^(请|帮我|给我|推荐|想看|我想看|关于|有关)+/u', '', $p);

        if(preg_match('/^(.*?)(片|剧|类)$/u', $p, $m) && mb_strlen($m[1], 'UTF-8') >= 2)
        {
            $p = $m[1];
        }

        $len = mb_strlen($p, 'UTF-8');
        if($len < 2 || $len > 12) continue;
        if(!in_array($p, $terms)) $terms[] = $p;
        if(count($terms) >= 4) break;
    }

    return $terms;
}

/** 检索候选影片 */
function ai_rec_fetch_candidates($terms)
{
    global $dsql;

    $fields = "v_id,tid,v_name,v_pic,v_tags,v_jq,v_publishyear,v_publisharea,v_douban,v_score,v_scorenum,v_note,v_actor,v_director,v_addtime,v_enname";
    $rows = array();
    $seen = array();

    if($terms)
    {
        $or = array();
        foreach($terms as $t)
        {
            $e = addslashes($t);
            $or[] = "(v_name LIKE '%{$e}%' OR v_tags LIKE '%{$e}%' OR v_jq LIKE '%{$e}%'"
                ." OR v_director LIKE '%{$e}%' OR v_actor LIKE '%{$e}%'"
                ." OR v_publisharea LIKE '%{$e}%' OR v_note LIKE '%{$e}%'"
                ." OR v_publishyear LIKE '%{$e}%')";
        }
        $first = addslashes($terms[0]);
        $sql = "SELECT {$fields} FROM sea_data WHERE v_recycled=0 AND v_name<>''"
            ." AND (".implode(' OR ', $or).")"
            ." ORDER BY (v_name LIKE '%{$first}%') DESC, v_id DESC LIMIT 40";

        $dsql->SetQuery($sql);
        $dsql->Execute('ai_rec_list');
        while($r = $dsql->GetArray('ai_rec_list'))
        {
            $id = intval($r['v_id']);
            if(isset($seen[$id])) continue;
            $seen[$id] = 1;
            $rows[] = $r;
        }
    }

    // 匹配不足时不再用“最新入库”硬凑，改为交给 AI 兜底补片名
    return $rows;
}

/** 数据库行 → 前端展示结构 */
function ai_rec_map_row($r)
{
    global $cfg_cmspath;

    $pic = trim($r['v_pic']);
    if($pic === '')
    {
        $pic = '/'.$cfg_cmspath.'pic/nopic.gif';
    }
    elseif(strpos($pic, '://') === false)
    {
        $pic = '/'.$cfg_cmspath.ltrim($pic, '/');
    }

    $scorenum = intval($r['v_scorenum']);
    $score = $scorenum > 0 ? round(floatval($r['v_score']) / $scorenum, 1) : 0;

    $genre = trim($r['v_jq']);
    if($genre === '') $genre = trim($r['v_tags']);
    $genre = str_replace(array('，', '、', '；', ';'), ',', $genre);

    $url = getContentLink(intval($r['tid']), intval($r['v_id']), 'link', date('Y-n', intval($r['v_addtime'])), $r['v_enname']);

    return array(
        'id'       => intval($r['v_id']),
        'name'     => $r['v_name'],
        'url'      => $url,
        'pic'      => $pic,
        'year'     => intval($r['v_publishyear']),
        'area'     => $r['v_publisharea'],
        'genre'    => $genre,
        'douban'   => round(floatval($r['v_douban']), 1),
        'score'    => $score,
        'note'     => $r['v_note'],
        'director' => $r['v_director'],
        'actor'    => $r['v_actor'],
    );
}

/** 按题材检索热门同类影片 */
function ai_rec_fetch_similar($seedId, $genres, $per = 10)
{
    global $dsql;

    $fields = "v_id,tid,v_name,v_pic,v_tags,v_jq,v_publishyear,v_publisharea,v_douban,v_score,v_scorenum,v_note,v_actor,v_director,v_addtime,v_enname";
    $rows = array();
    $seen = array();

    foreach(array_slice($genres, 0, 3) as $i => $g)
    {
        $g = trim($g);
        if($g === '' || mb_strlen($g, 'UTF-8') < 2) continue;

        $e = addslashes($g);
        $sql = "SELECT {$fields} FROM sea_data WHERE v_recycled=0 AND v_id<>{$seedId}"
            ." AND (v_jq LIKE '%{$e}%' OR v_tags LIKE '%{$e}%')"
            ." ORDER BY v_hit DESC, v_id DESC LIMIT {$per}";
        $dsql->SetQuery($sql);
        $dsql->Execute('ai_rec_sim_'.$i);
        while($r = $dsql->GetArray('ai_rec_sim_'.$i))
        {
            $id = intval($r['v_id']);
            if(isset($seen[$id])) continue;
            $seen[$id] = 1;
            $rows[] = $r;
        }
    }

    return $rows;
}

/** 按片名查找站内影片：优先精确匹配，其次带版本后缀的前缀匹配 */
function ai_rec_find_movie($title, $fields = 'v_id,tid,v_name,v_pic,v_addtime,v_enname')
{
    global $dsql;
    $e = addslashes($title);
    $base = "SELECT {$fields} FROM sea_data WHERE v_recycled=0 AND v_name<>''";

    $row = $dsql->GetOne($base." AND v_name='{$e}'");
    if(is_array($row)) return $row;

    // 去掉片名中的空格再试前缀，兼容“咒术回战 第二季”与“咒术回战第二季”
    $search = preg_replace('/\s+/u', '', $title);
    if($search === '') return '';
    $e2 = addslashes($search);

    // 只接受“国语版/第二季/电影版”等后缀，避免“繁花”误匹配到“繁花错”
    $dsql->SetQuery($base." AND v_name LIKE '{$e2}%' ORDER BY CHAR_LENGTH(v_name) ASC, v_id DESC LIMIT 20");
    $dsql->Execute('ai_rec_movie');
    while($r = $dsql->GetArray('ai_rec_movie'))
    {
        $rest = mb_substr($r['v_name'], mb_strlen($search, 'UTF-8'), null, 'UTF-8');
        $rest = ltrim($rest, " .·-—_：:（(【[");
        if($rest === '' || preg_match('/^(国语|粤语|英语|普通话|中字|双语|高清|蓝光|超清|完整|未删减|电影|电视剧|剧集|动画|动漫|第[0-9一二三四五六七八九十]+季|特别篇|番外|解说|合集)/u', $rest))
        {
            return $r;
        }
    }

    return '';
}

/** 热门推荐缓存文件 */
function ai_rec_hot_file()
{
    return __DIR__.'/data/cache/ai_hot_recommend.json';
}

/** 读取热门推荐缓存 */
function ai_rec_read_hot($ttl = 21600)
{
    $file = ai_rec_hot_file();
    if(!file_exists($file)) return array('fresh'=>false, 'items'=>array());

    $raw = @file_get_contents($file);
    $data = $raw ? json_decode($raw, true) : null;
    if(!is_array($data) || !isset($data['time']) || !isset($data['items']) || !is_array($data['items']))
    {
        return array('fresh'=>false, 'items'=>array());
    }

    return array(
        'fresh' => (time() - intval($data['time']) < $ttl),
        'items' => $data['items'],
    );
}

/** 把 AI 给出的热门片名匹配到站内影片 */
function ai_rec_match_hot($items)
{
    global $dsql, $cfg_cmspath;

    $out = array();
    $seen = array();
    foreach($items as $it)
    {
        if(!is_array($it)) continue;

        $title = isset($it['title']) ? trim($it['title']) : '';
        if($title === '' || isset($seen[$title])) continue;
        $seen[$title] = 1;

        $item = array(
            'title'   => $title,
            'type'    => isset($it['type']) ? $it['type'] : '影视',
            'reason'  => isset($it['reason']) ? $it['reason'] : '',
            'matched' => 0,
            'name'    => '',
            'url'     => '',
            'pic'     => '',
        );

        $row = ai_rec_find_movie($title);

        if(is_array($row))
        {
            $item['matched'] = 1;
            $item['name'] = $row['v_name'];
            $item['url'] = getContentLink(intval($row['tid']), intval($row['v_id']), 'link', date('Y-n', intval($row['v_addtime'])), $row['v_enname']);

            $pic = trim($row['v_pic']);
            if($pic === '')
            {
                $pic = '/'.$cfg_cmspath.'pic/nopic.gif';
            }
            elseif(strpos($pic, '://') === false)
            {
                $pic = '/'.$cfg_cmspath.ltrim($pic, '/');
            }
            $item['pic'] = $pic;
        }

        $out[] = $item;
        if(count($out) >= 12) break;
    }

    return $out;
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if($action === 'status')
{
    $c = ai_rec_config();
    ai_rec_out(array(
        'ok'      => true,
        'enabled' => ($c['open'] === 'on' && $c['recommend'] === 'on' && $c['api_key'] !== ''),
    ));
}

// ===== AI 消耗型接口：仅允许 POST，且校验同源 =====
if($action === 'chat' || $action === 'hot')
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
    if($method !== 'POST')
    {
        ai_rec_out(array('ok'=>false, 'error'=>'请求方式不被允许'));
    }
    if(!ai_rec_same_origin())
    {
        ai_rec_out(array('ok'=>false, 'error'=>'请求来源校验失败'));
    }
}

// ===== 功能开关校验 =====
$c = ai_rec_config();
if($c['open'] !== 'on' || $c['recommend'] !== 'on' || $c['api_key'] === '')
{
    ai_rec_out(array('ok'=>false, 'error'=>'AI 影片推荐功能未开启'));
}

require_once(__DIR__.'/include/common.php');
require_once(sea_INC.'/ai.php');

// ===== 近期热门影视 =====
if($action === 'hot')
{
    $cache = ai_rec_read_hot(21600);
    $items = $cache['items'];

    if(!$cache['fresh'])
    {
        $res = deepseek_hot_movies(isset($cfg_webname) ? $cfg_webname : '', 12);
        if($res['ok'] && count($res['items']) > 0)
        {
            $matched = ai_rec_match_hot($res['items']);
            if(count($matched) > 0)
            {
                $items = $matched;
                $payload = json_encode(array('time'=>time(), 'items'=>$items), JSON_UNESCAPED_UNICODE);
                @file_put_contents(ai_rec_hot_file(), $payload, LOCK_EX);
            }
        }
        elseif(!$res['ok'] && !empty($res['limited']) && (!is_array($items) || count($items) === 0))
        {
            ai_rec_out(array('ok'=>false, 'error'=>'AI 服务暂不可用'));
        }
    }

    if(!is_array($items) || count($items) === 0)
    {
        ai_rec_out(array('ok'=>false, 'error'=>'暂时没有获取到热门影视资源'));
    }

    ai_rec_out(array('ok'=>true, 'items'=>$items));
}

if($action !== 'chat')
{
    ai_rec_out(array('ok'=>false, 'error'=>'未知操作'));
}

// ===== 访客输入 =====
$message = isset($_POST['message']) ? $_POST['message'] : '';
if(!is_string($message)) $message = '';
$message = trim(strip_tags($message));
$message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message);
if($message === '')
{
    ai_rec_out(array('ok'=>false, 'error'=>'请先输入您想看的内容'));
}
if(mb_strlen($message, 'UTF-8') > 300)
{
    $message = mb_substr($message, 0, 300, 'UTF-8');
}

// ===== 最近对话上下文 =====
$history = array();
if(isset($_POST['history']) && is_string($_POST['history']))
{
    $rawHistory = json_decode($_POST['history'], true);
    if(is_array($rawHistory))
    {
        foreach($rawHistory as $h)
        {
            if(!is_array($h)) continue;
            $role = isset($h['role']) ? $h['role'] : '';
            $content = isset($h['content']) && is_string($h['content']) ? trim(strip_tags($h['content'])) : '';
            if(($role === 'user' || $role === 'assistant') && $content !== '')
            {
                $history[] = array(
                    'role'    => $role,
                    'content' => mb_substr($content, 0, 300, 'UTF-8'),
                );
            }
        }
        $history = array_slice($history, -6);
    }
}

// ===== 精确影片模式：热门标签点击后直接查该片 =====
$siteName = isset($cfg_webname) ? $cfg_webname : '';
$exactTitle = isset($_POST['exact_title']) ? trim(strip_tags($_POST['exact_title'])) : '';
$exactTitle = trim($exactTitle, "《》“”\"' ");
if(mb_strlen($exactTitle, 'UTF-8') > 40) $exactTitle = '';

if($exactTitle !== '')
{
    $exactRow = ai_rec_find_movie($exactTitle);
    if(is_array($exactRow))
    {
        $candidate = ai_rec_map_row($exactRow);
        $candidate['reason'] = '你搜索的影片';

        $question = '请介绍《'.$exactRow['v_name'].'》：讲的是什么、适合什么人看、值不值得看。只介绍这一部，不要推荐其他影片。';
        $result = deepseek_recommend_movies($question, array($candidate), $history, $siteName);

        if($result['ok'])
        {
            $movies = $result['movies'];
            if(count($movies) === 0) $movies = array($candidate);
            ai_rec_out(array('ok'=>true, 'reply'=>$result['reply'], 'movies'=>$movies));
        }

        if(!empty($result['limited']))
        {
            ai_rec_out(array('ok'=>false, 'error'=>'AI 服务暂不可用'));
        }

        ai_rec_out(array(
            'ok'=>true,
            'reply'=>'为你找到《'.$exactRow['v_name'].'》，点击卡片查看详情。',
            'movies'=>array($candidate),
        ));
    }
    // 站内找不到时，继续走正常搜索与 AI 兜底
}

// ===== 检索候选片单并请求 AI =====
$terms = ai_rec_terms($message);

// “类似《XXX》”场景：用种子影片的类型检索同类热门影片
$seedSimilar = array();
if(preg_match('/《([^》]{1,40})》/u', $message, $seedMatch))
{
    $seedTitle = trim($seedMatch[1]);
    if($seedTitle !== '')
    {
        $seed = ai_rec_find_movie($seedTitle, 'v_id,v_jq,v_tags');

        if(is_array($seed))
        {
            $genres = array();
            $genreStr = trim($seed['v_jq']) !== '' ? $seed['v_jq'] : $seed['v_tags'];
            $genreStr = str_replace(array('，','、','；',';','/','|'), ',', $genreStr);
            foreach(explode(',', $genreStr) as $g)
            {
                $g = trim($g);
                if($g === '' || mb_strlen($g, 'UTF-8') < 2) continue;
                if(!in_array($g, $genres)) $genres[] = $g;
                if(!in_array($g, $terms)) $terms[] = $g;
                if(count($terms) >= 6) break;
            }

            $seedSimilar = ai_rec_fetch_similar(intval($seed['v_id']), $genres, 10);
        }
    }
}

$rows = ai_rec_fetch_candidates($terms);

// 同类影片优先进入候选片单
if(!empty($seedSimilar))
{
    $merged = array();
    $seenIds = array();
    foreach(array_merge($seedSimilar, $rows) as $r)
    {
        $id = intval($r['v_id']);
        if(isset($seenIds[$id])) continue;
        $seenIds[$id] = 1;
        $merged[] = $r;
        if(count($merged) >= 40) break;
    }
    $rows = $merged;
}

// 关键词匹配过少时，让 AI 给出片名，再回站内精确查找
if(count($rows) < 5)
{
    $suggest = deepseek_suggest_titles($message, $siteName, 10);
    if(!empty($suggest['ok']) && !empty($suggest['titles']))
    {
        $seenIds = array();
        foreach($rows as $r) $seenIds[intval($r['v_id'])] = 1;

        foreach($suggest['titles'] as $title)
        {
            $r = ai_rec_find_movie($title);
            if(!is_array($r)) continue;

            $id = intval($r['v_id']);
            if(isset($seenIds[$id])) continue;
            $seenIds[$id] = 1;
            $rows[] = $r;
            if(count($rows) >= 20) break;
        }
    }
}

// 站内确实没有相关资源时，返回不含卡片的友好提示
if(count($rows) === 0)
{
    ai_rec_out(array(
        'ok'     => true,
        'reply'  => '站内暂时没有找到匹配的影片，可以换个类型、演员或年代再试试。',
        'movies' => array(),
    ));
}

$candidates = array();
foreach($rows as $r)
{
    $candidates[] = ai_rec_map_row($r);
}

// 把热门缓存中已匹配站内的片名作为推荐风格参考
$hotTitles = array();
$hotCache = ai_rec_read_hot(21600);
if($hotCache['fresh'] && is_array($hotCache['items']))
{
    foreach($hotCache['items'] as $h)
    {
        if(!empty($h['matched']) && !empty($h['name'])) $hotTitles[] = $h['name'];
        if(count($hotTitles) >= 8) break;
    }
}

$result = deepseek_recommend_movies($message, $candidates, $history, $siteName, $hotTitles);

if(!$result['ok'])
{
    ai_rec_out(array('ok'=>false, 'error'=>isset($result['error']) ? $result['error'] : 'AI 服务暂时不可用'));
}

ai_rec_out(array(
    'ok'     => true,
    'reply'  => $result['reply'],
    'movies' => $result['movies'],
));
