<?php
/**
 * AI 大模型通用模块（OpenAI 兼容协议）
 *
 * 配置文件位置: /data/admin/ai.php（兼容旧 deepseek.php）
 * 支持任意 OpenAI 兼容接口：DeepSeek / OpenAI / 通义千问 / Moonshot / 火山 / 自建中转 等
 *
 * 配置变量名：
 *   $ai_open            = 'on' | 'off'
 *   $ai_provider        = 'custom' | 'deepseek' | 'openai' | 'qwen' | 'moonshot' | 'volcengine'
 *   $ai_api_url         = 'https://api.deepseek.com/v1'
 *   $ai_api_key         = 'sk-xxxx'
 *   $ai_model           = 'deepseek-chat'
 *   $ai_temperature     = '0.7'
 *   $ai_max_tokens      = '800'
 *   $ai_search_correct  = 'on' | 'off'
 *
 * 兼容旧变量名（自动 fallback）：$deepseek_open / $deepseek_api_url / ... 仍可用
 */

if(!defined('sea_INC'))
{
    if(defined('sea_ADMIN'))
    {
        define('sea_INC', dirname(__FILE__));
    }
    else
    {
        exit("Request Error!");
    }
}

/**
 * 加载 AI 配置（自动新旧变量名兼容 + 文件兜底）
 *
 * @return array
 */
function deepseek_load_config()
{
    static $cfg = null;
    if($cfg !== null) return $cfg;

    // ===== 默认值 =====
    $cfg = array(
        'open'            => 'off',
        'provider'        => 'custom',
        'api_url'         => 'https://api.deepseek.com/v1',
        'api_key'         => '',
        'model'           => 'deepseek-chat',
        'temperature'     => '0.7',
        'max_tokens'      => '800',
        'ai_search_correct' => 'off',
        'ai_recommend'    => 'off',
        'limit_daily'     => 0,
        'limit_minute'    => 0,
        'usage_date'      => '',
        'usage_day'       => 0,
        'usage_minute_key'=> '',
        'usage_minute'    => 0,
    );

    // ===== 旧 → 新 变量名映射表 =====
    $legacyMap = array(
        'deepseek_open'        => 'open',
        'deepseek_api_url'     => 'api_url',
        'deepseek_api_key'     => 'api_key',
        'deepseek_model'       => 'model',
        'deepseek_temperature' => 'temperature',
    );

    // 1) 先尝试新变量名（config.php 已 extract）
    global $ai_open, $ai_provider, $ai_api_url, $ai_api_key, $ai_model, $ai_temperature, $ai_max_tokens, $ai_search_correct, $ai_recommend;
    global $ai_limit_daily, $ai_limit_minute, $ai_usage_date, $ai_usage_day, $ai_usage_minute_key, $ai_usage_minute;
    if(isset($ai_open))            $cfg['open']            = $ai_open;
    if(isset($ai_provider))        $cfg['provider']        = $ai_provider;
    if(isset($ai_api_url))         $cfg['api_url']         = $ai_api_url;
    if(isset($ai_api_key))         $cfg['api_key']         = $ai_api_key;
    if(isset($ai_model))           $cfg['model']           = $ai_model;
    if(isset($ai_temperature))     $cfg['temperature']     = $ai_temperature;
    if(isset($ai_max_tokens))      $cfg['max_tokens']      = $ai_max_tokens;
    if(isset($ai_search_correct))  $cfg['ai_search_correct'] = $ai_search_correct;
    if(isset($ai_recommend))       $cfg['ai_recommend']    = $ai_recommend;
    if(isset($ai_limit_daily))     $cfg['limit_daily']     = intval($ai_limit_daily);
    if(isset($ai_limit_minute))    $cfg['limit_minute']    = intval($ai_limit_minute);
    if(isset($ai_usage_date))      $cfg['usage_date']      = $ai_usage_date;
    if(isset($ai_usage_day))       $cfg['usage_day']       = intval($ai_usage_day);
    if(isset($ai_usage_minute_key))$cfg['usage_minute_key']= $ai_usage_minute_key;
    if(isset($ai_usage_minute))    $cfg['usage_minute']    = intval($ai_usage_minute);

    // 2) 旧变量名 fallback（兼容老 deepseek.php 配置文件）
    global $deepseek_open, $deepseek_api_url, $deepseek_api_key, $deepseek_model, $deepseek_temperature;
    foreach($legacyMap as $legacy => $modern)
    {
        if(empty($cfg[$modern]) && isset($$legacy))
        {
            $cfg[$modern] = $$legacy;
        }
    }

    // 3) api_url 兜底：如果老配置里 api_key 有值但 api_url 空，说明是 DeepSeek 老配置
    if(empty($cfg['api_url']) && !empty($cfg['api_key']))
    {
        $cfg['api_url'] = 'https://api.deepseek.com/v1';
    }

    // 4) 文件兜底（新配置文件路径 /data/admin/ai.php，兼容旧 deepseek.php）
    $cfgFile = dirname(__FILE__).'/../data/admin/ai.php';
    if(!file_exists($cfgFile))
    {
        $cfgFile = dirname(__FILE__).'/../data/admin/deepseek.php';
    }
    if(file_exists($cfgFile))
    {
        include $cfgFile;
        // 新变量名
        if(isset($ai_open))            $cfg['open']            = $ai_open;
        if(isset($ai_provider))        $cfg['provider']        = $ai_provider;
        if(isset($ai_api_url))         $cfg['api_url']         = $ai_api_url;
        if(isset($ai_api_key))         $cfg['api_key']         = $ai_api_key;
        if(isset($ai_model))           $cfg['model']           = $ai_model;
        if(isset($ai_temperature))     $cfg['temperature']     = $ai_temperature;
        if(isset($ai_max_tokens))      $cfg['max_tokens']      = $ai_max_tokens;
        if(isset($ai_search_correct))  $cfg['ai_search_correct'] = $ai_search_correct;
        if(isset($ai_recommend))       $cfg['ai_recommend']    = $ai_recommend;
        if(isset($ai_limit_daily))     $cfg['limit_daily']     = intval($ai_limit_daily);
        if(isset($ai_limit_minute))    $cfg['limit_minute']    = intval($ai_limit_minute);
        if(isset($ai_usage_date))      $cfg['usage_date']      = $ai_usage_date;
        if(isset($ai_usage_day))       $cfg['usage_day']       = intval($ai_usage_day);
        if(isset($ai_usage_minute_key))$cfg['usage_minute_key']= $ai_usage_minute_key;
        if(isset($ai_usage_minute))    $cfg['usage_minute']    = intval($ai_usage_minute);
        // 旧变量名兜底
        foreach($legacyMap as $legacy => $modern)
        {
            if(($cfg[$modern] === 'off' || $cfg[$modern] === '' || $cfg[$modern] === null) && isset($$legacy))
            {
                $cfg[$modern] = $$legacy;
            }
        }
    }

    // 5) max_tokens 安全下限
    $cfg['max_tokens'] = max(100, intval($cfg['max_tokens']));

    return $cfg;
}

/**
 * AI 配置文件路径
 */
function deepseek_ai_config_path()
{
    return dirname(__FILE__).'/../data/admin/ai.php';
}

/**
 * 配置文件读写锁（用于用量计数的原子更新）
 */
function deepseek_ai_lock()
{
    $lockFile = deepseek_ai_config_path().'.lock';
    $fp = @fopen($lockFile, 'c');
    if(!$fp) return false;
    if(!flock($fp, LOCK_EX))
    {
        fclose($fp);
        return false;
    }
    return $fp;
}

/**
 * 读取配置文件中的变量（不依赖 deepseek_load_config 的静态缓存）
 */
function deepseek_ai_read_vars($path)
{
    if(!is_file($path)) return array();

    $__ai_vars = array();
    $__ai_file = $path;
    ob_start();
    include $__ai_file;
    ob_end_clean();

    $keys = array(
        'ai_open', 'ai_provider', 'ai_api_url', 'ai_api_key', 'ai_model', 'ai_temperature',
        'ai_max_tokens', 'ai_search_correct', 'ai_recommend',
        'ai_limit_daily', 'ai_limit_minute',
        'ai_usage_date', 'ai_usage_day', 'ai_usage_minute_key', 'ai_usage_minute',
    );
    foreach($keys as $k)
    {
        if(isset($$k)) $__ai_vars[$k] = $$k;
    }
    return $__ai_vars;
}

/**
 * 原子写入配置文件
 */
function deepseek_ai_write_vars($path, $vars)
{
    $order = array(
        'ai_open', 'ai_provider', 'ai_api_url', 'ai_api_key', 'ai_model', 'ai_temperature',
        'ai_max_tokens', 'ai_search_correct', 'ai_recommend',
        'ai_limit_daily', 'ai_limit_minute',
        'ai_usage_date', 'ai_usage_day', 'ai_usage_minute_key', 'ai_usage_minute',
    );
    $intKeys = array('ai_limit_daily', 'ai_limit_minute', 'ai_usage_day', 'ai_usage_minute');

    $str = '<?php  ';
    foreach($order as $k)
    {
        $v = isset($vars[$k]) ? $vars[$k] : (in_array($k, $intKeys, true) ? 0 : '');
        if(in_array($k, $intKeys, true))
        {
            $str .= '$'.$k.' = '.max(0, intval($v)).'; ';
        }
        else
        {
            $str .= '$'.$k.' = "'.addslashes((string)$v).'"; ';
        }
    }
    $str .= " ?>";

    $tmp = $path.'.tmp.'.getmypid();
    if(@file_put_contents($tmp, $str, LOCK_EX) === false) return false;
    if(!@rename($tmp, $path))
    {
        @unlink($tmp);
        return false;
    }
    return true;
}

/**
 * 前台 AI 调用限额检查
 *
 * 返回 true 表示放行并计 1 次；false 表示已超限。
 * 管理后台定义 AI_LIMIT_BYPASS 常量后不限额、不计数。
 * 每日/每分钟限额为 0 时表示不限额。
 */
function deepseek_limit_allow()
{
    if(defined('AI_LIMIT_BYPASS') && AI_LIMIT_BYPASS) return true;

    $path = deepseek_ai_config_path();
    $lock = deepseek_ai_lock();
    $vars = deepseek_ai_read_vars($path);

    $dailyLimit  = isset($vars['ai_limit_daily'])  ? max(0, intval($vars['ai_limit_daily']))  : 0;
    $minuteLimit = isset($vars['ai_limit_minute']) ? max(0, intval($vars['ai_limit_minute'])) : 0;

    if($dailyLimit <= 0 && $minuteLimit <= 0)
    {
        if($lock) { flock($lock, LOCK_UN); fclose($lock); }
        return true;
    }

    $today     = date('Y-m-d');
    $minuteKey = date('Y-m-d H:i');

    $usageDate      = isset($vars['ai_usage_date']) ? $vars['ai_usage_date'] : '';
    $usageDay       = isset($vars['ai_usage_day']) ? max(0, intval($vars['ai_usage_day'])) : 0;
    $usageMinuteKey = isset($vars['ai_usage_minute_key']) ? $vars['ai_usage_minute_key'] : '';
    $usageMinute    = isset($vars['ai_usage_minute']) ? max(0, intval($vars['ai_usage_minute'])) : 0;

    if($usageDate !== $today)
    {
        $usageDate = $today;
        $usageDay = 0;
    }
    if($usageMinuteKey !== $minuteKey)
    {
        $usageMinuteKey = $minuteKey;
        $usageMinute = 0;
    }

    $allowed = !(
        ($dailyLimit > 0 && $usageDay >= $dailyLimit) ||
        ($minuteLimit > 0 && $usageMinute >= $minuteLimit)
    );

    if($allowed)
    {
        $usageDay++;
        $usageMinute++;
    }

    $vars['ai_usage_date'] = $usageDate;
    $vars['ai_usage_day'] = $usageDay;
    $vars['ai_usage_minute_key'] = $usageMinuteKey;
    $vars['ai_usage_minute'] = $usageMinute;
    deepseek_ai_write_vars($path, $vars);

    if($lock) { flock($lock, LOCK_UN); fclose($lock); }
    return $allowed;
}

/**
 * 从 AI 回复文本中提取第一个完整的 JSON 对象或数组
 *
 * 为什么需要这个？
 *   OpenAI 协议的 response_format=json_object 只有部分模型支持（GPT / DeepSeek / Moonshot 支持，
 *   通义千问 qwen-turbo/qwen-plus 不支持）。所以我们去掉了这个硬约束，
 *   改成在 prompt 里要求 "输出严格 JSON"，然后 PHP 端用正则兜底提取。
 *
 * @param string $content AI 原始回复
 * @return string|null 第一个完整 JSON 块，失败返回 null
 */
function deepseek_extract_json($content)
{
    $content = trim($content);
    if($content === '') return null;

    // 1) 直接 json_decode 尝试（AI 真吐了纯 JSON 的情况）
    $d = json_decode($content, true);
    if(is_array($d)) return $content;

    // 2) 剥离 ```json ... ``` 代码块标记
    $stripped = preg_replace('/^\s*```(?:json|JSON)?\s*/', '', $content);
    $stripped = preg_replace('/\s*```\s*$/', '', $stripped);
    $d2 = json_decode(trim($stripped), true);
    if(is_array($d2)) return trim($stripped);

    // 3) 平衡括号算法，找第一个完整 {} 或 [] 块
    $len = strlen($content);
    $start = -1;
    $depth = 0;
    $inStr = false;
    $escape = false;
    $char = '';

    for($i = 0; $i < $len; $i++)
    {
        $char = $content[$i];
        if($escape) { $escape = false; continue; }
        if($char === '\\' && $inStr) { $escape = true; continue; }
        if($char === '"') { $inStr = !$inStr; continue; }
        if($inStr) continue;

        if(($char === '{' || $char === '[') && $start === -1)
        {
            $start = $i;
            $depth = 1;
        }
        elseif($start !== -1 && ($char === '{' || $char === '['))
        {
            $depth++;
        }
        elseif($start !== -1 && ($char === '}' || $char === ']'))
        {
            $depth--;
            if($depth === 0)
            {
                $chunk = substr($content, $start, $i - $start + 1);
                $d3 = json_decode($chunk, true);
                if(is_array($d3)) return $chunk;
                $start = -1;
            }
        }
    }

    // 4) 兜底：用最简正则匹配（嵌套可能不准，但简单场景够用）
    if(preg_match('/\{[^{}]*\}/', $content, $m))
    {
        $d4 = json_decode($m[0], true);
        if(is_array($d4)) return $m[0];
    }

    return null;
}

/**
 * 调用 AI Chat Completions（OpenAI 兼容协议）
 *
 * @param array  $messages       对话历史 [['role'=>'system'|'user','content'=>'...'], ...]
 * @param float  $temperature    默认 0.7
 * @param bool   $expectJson     是否期望返回 JSON（影响 prompt 提示 + max_tokens 翻倍）
 * @param int    $timeoutSeconds 超时秒数（默认 60；搜索纠正场景建议 5）
 * @return array ['ok'=>true,'content'=>'...'] 或 ['ok'=>false,'error'=>'...']
 */
function deepseek_chat($messages, $temperature = 0.7, $expectJson = false, $timeoutSeconds = 60)
{
    $cfg = deepseek_load_config();

    if($cfg['open'] !== 'on')
    {
        return array('ok'=>false, 'error'=>'AI 功能未开启');
    }
    if(empty($cfg['api_key']))
    {
        return array('ok'=>false, 'error'=>'AI API Key 未配置');
    }

    // 前台 AI 调用限额（管理后台定义 AI_LIMIT_BYPASS 后不限额、不计数）
    if(!deepseek_limit_allow())
    {
        return array('ok'=>false, 'error'=>'AI 服务暂不可用', 'limited'=>true);
    }

    // 拼接口地址：兼容用户是否漏了 /chat/completions
    $apiUrl = rtrim($cfg['api_url'], '/');
    if(stripos($apiUrl, '/chat/completions') === false)
    {
        $apiUrl .= '/chat/completions';
    }

    // max_tokens：JSON 场景翻倍，避免截断 description
    $maxTokens = intval($cfg['max_tokens']);
    if($expectJson) $maxTokens = $maxTokens * 2; // 800 → 1600

    $payload = array(
        'model'       => $cfg['model'],
        'messages'    => $messages,
        'temperature' => floatval($temperature),
        'max_tokens'  => $maxTokens,
        'stream'      => false,
    );

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $apiUrl,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => intval($timeoutSeconds),
        CURLOPT_CONNECTTIMEOUT => min(5, intval($timeoutSeconds)),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$cfg['api_key'],
        ),
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ));

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if($raw === false)
    {
        return array('ok'=>false, 'error'=>'CURL 错误: '.$curlErr);
    }
    if($httpCode !== 200)
    {
        return array('ok'=>false, 'error'=>"HTTP {$httpCode}: {$raw}");
    }

    $resp = json_decode($raw, true);
    if(!is_array($resp))
    {
        return array('ok'=>false, 'error'=>'API 响应 JSON 解析失败: '.$raw);
    }

    if(isset($resp['error']))
    {
        return array('ok'=>false, 'error'=>'API 错误: '.json_encode($resp['error'], JSON_UNESCAPED_UNICODE));
    }

    // 取 assistant 回复
    $content = '';
    if(!empty($resp['choices'][0]['message']['content']))
    {
        $content = $resp['choices'][0]['message']['content'];
    }

    return array('ok'=>true, 'content'=>$content);
}

/**
 * 海洋 CMS AI 帮助问答（后台右下角 AI 帮助按钮）
 *
 * @param string $question
 * @param string $context
 * @return array
 */
function deepseek_ask_help($question, $context = '')
{
    $question = trim($question);
    if($question === '')
    {
        return array('ok'=>false, 'error'=>'请输入您的问题');
    }

    $systemPrompt = <<<'SYS'
你是海洋 CMS（SeaCMS）的资深技术支持专家。你非常熟悉海洋 CMS 的后台结构、模板语法、采集规则、会员系统、支付接口、SEO 设置、视频播放数据、资源库API、自定义字段、jqtype 剧情分类、v_tags 关键词、v_director/v_actor 字段、静态化、FTP 远程发布、SMTP 邮件、IP 安全、AI 增强等所有功能。

回答要求：
1. 用中文简洁回答，优先给出可操作的步骤/代码/配置示例。
2. 涉及海洋 CMS 时，给出准确的字段名、表名（sea_data、sea_content、sea_playdata、sea_jqtype 等）、模板标签、后台菜单路径。
3. 如果问题超出范围，直接说"抱歉，我主要解答海洋 CMS 相关问题"。
4. 回答分点清晰，避免大段文字堆砌。
SYS;

    $userContent = "用户问题：{$question}\n\n";
    if(!empty($context))
    {
        $userContent .= "【当前页面上下文】\n{$context}\n\n";
        $userContent .= "请结合以上上下文，针对用户的海洋 CMS 问题给出解答。";
    }
    else
    {
        $userContent .= "请针对用户的海洋 CMS 问题给出解答。";
    }

    $messages = array(
        array('role' => 'system', 'content' => $systemPrompt),
        array('role' => 'user',   'content' => $userContent),
    );

    $result = deepseek_chat($messages, 0.3, false);
    if(!$result['ok']) return $result;

    return array('ok'=>true, 'answer'=>trim($result['content']));
}

/**
 * AI 搜索关键词纠正（前台 search.php）
 *
 * @param string $keyword
 * @return array ['ok'=>true, 'original'=>'...', 'corrected'=>'...', 'changed'=>bool]
 */
function deepseek_correct_keyword($keyword)
{
    $keyword = trim($keyword);
    if($keyword === '')
    {
        return array('ok'=>false, 'error'=>'关键词为空');
    }

    $cfg = deepseek_load_config();
    if($cfg['open'] !== 'on' || $cfg['ai_search_correct'] !== 'on' || empty($cfg['api_key']))
    {
        return array('ok'=>false, 'error'=>'AI搜索辅助未开启');
    }

    // 过短关键词跳过（AI 瞎改风险高）
    if(mb_strlen($keyword) <= 2)
    {
        return array('ok'=>true, 'original'=>$keyword, 'corrected'=>$keyword, 'changed'=>false);
    }

    $systemPrompt = <<<'SYS'
你是中文影视搜索关键词纠正助手。判断用户输入的影视搜索关键词是否存在错别字、谐音误用、口语化、常见打字错误，并给出纠正建议。

规则：
1. 正确的关键词 → 只输出: ORIGINAL
2. 需要纠正 → 只输出: CORRECTED:纠正后的词
3. 保持保守：只改"大概率是错的"情况；拿不准就输出 ORIGINAL
4. 专有名词、片名、人名、导演演员名、外语、正确英文一律输出 ORIGINAL
SYS;

    $messages = array(
        array('role' => 'system', 'content' => $systemPrompt),
        array('role' => 'user',   'content' => "纠正这个影视搜索关键词：{$keyword}"),
    );

    $result = deepseek_chat($messages, 0.0, false, 5);
    if(!$result['ok'])
    {
        if(!empty($result['limited']))
        {
            return array('ok'=>false, 'error'=>'AI 服务暂不可用', 'limited'=>true);
        }
        return array('ok'=>true, 'original'=>$keyword, 'corrected'=>$keyword, 'changed'=>false);
    }

    $content = trim($result['content']);
    if(stripos($content, 'CORRECTED:') !== false)
    {
        $corrected = trim(substr($content, strpos($content, ':') + 1));
        $corrected = trim($corrected, "\"'。.,.!?）)】]》 \t\r\n");
        if($corrected !== '' && $corrected !== $keyword)
        {
            return array('ok'=>true, 'original'=>$keyword, 'corrected'=>$corrected, 'changed'=>true);
        }
    }

    return array('ok'=>true, 'original'=>$keyword, 'corrected'=>$keyword, 'changed'=>false);
}

/**
 * 主入口：根据影片名称拉取 AI 增强信息
 *
 * @param string $videoName
 * @return array ['ok'=>true, 'data'=> [...]] 或 ['ok'=>false, 'error'=>'...']
 */
function deepseek_fetch_video($videoName)
{
    $videoName = trim($videoName);
    if($videoName === '')
    {
        return array('ok'=>false, 'error'=>'影片名称不能为空');
    }

    $systemPrompt = <<<'SYS'
你是资深影视资料编辑，擅长中文影视资料整理。请根据用户提供的影片名称，联网核实并补全影片信息。严格遵守以下要求：

1. 输出必须是严格的 JSON 对象（不要任何 Markdown、代码块标记、解释文字），像 {"directors":"...", ...} 这样。
2. 所有用逗号分隔的字段必须只使用英文逗号 "," 分隔。
3. 字段含义如下：
   - directors      : 导演，英文逗号","分隔（如"郭帆,张艺谋"）
   - actors         : 主要演员 3-6 位，英文逗号","分隔（如"吴京,屈楚萧,李光洁"）
   - keywords       : 核心关键词 5-8 个，英文逗号","分隔（如"科幻,流浪地球,郭帆,吴京,中国大陆,2019,灾难"）
   - jqtype         : 剧情小分类 2-3 个，英文逗号","分隔（如"科幻,冒险,灾难"）
   - release_year   : 发行年份 4 位数字，找不到填空字符串
   - language       : 主要语言（如"汉语普通话""英语"），找不到填空字符串
   - region         : 发行地区（如"中国大陆""美国"），找不到填空字符串
   - douban_score   : 豆瓣评分 0-10 数字，找不到填空字符串
   - description    : 600 字左右的影片介绍，4 段式：①剧情梗概 ②阵容幕后 ③主题反响 ④综合评价；段落间空行；SEO 规范；550-650 字
4. 严禁编造数据，拿不准的字段一律填空字符串 ""。

输出示例（严格 JSON，不要任何其他文字）：
{"directors":"郭帆","actors":"吴京,屈楚萧,李光洁","keywords":"科幻,流浪地球,郭帆,吴京,中国大陆,2019,灾难","jqtype":"科幻,冒险,灾难","release_year":"2019","language":"汉语普通话","region":"中国大陆","douban_score":"7.9","description":"..."}
SYS;

    $messages = array(
        array('role' => 'system', 'content' => $systemPrompt),
        array('role' => 'user',   'content' => "请补全以下影片的资料：《{$videoName}》"),
    );

    $result = deepseek_chat($messages, 0.7, true);  // expectJson=true → max_tokens 翻倍
    if(!$result['ok']) return $result;

    // ===== JSON 提取：兼容不同模型 =====
    $raw = trim($result['content']);
    $jsonStr = deepseek_extract_json($raw);
    if($jsonStr === null)
    {
        return array('ok'=>false, 'error'=>'AI 返回非合法 JSON: '.$raw);
    }
    $data = json_decode($jsonStr, true);
    if(!is_array($data))
    {
        return array('ok'=>false, 'error'=>'AI 返回非合法 JSON: '.$jsonStr);
    }

    // 规范化字段（白名单过滤 + 类型修正）
    $schema = array(
        'directors'     => '',
        'actors'        => '',
        'keywords'      => '',
        'jqtype'        => '',
        'release_year'  => '',
        'language'      => '',
        'region'        => '',
        'douban_score'  => '',
        'description'   => '',
    );
    foreach($schema as $k=>$v)
    {
        if(isset($data[$k]) && is_string($data[$k]))
        {
            $schema[$k] = trim($data[$k]);
        }
    }

    // 分隔符统一清洗
    $commaKeys = array('directors', 'actors', 'keywords', 'jqtype');
    foreach($commaKeys as $k)
    {
        if($schema[$k] !== '')
        {
            $schema[$k] = str_replace(array('，', '、', '；', ';', '/'), ',', $schema[$k]);
            $schema[$k] = preg_replace('/\s*,\s*/u', ',', $schema[$k]);
            $schema[$k] = trim($schema[$k], ',');
        }
    }

    return array('ok'=>true, 'data'=>$schema);
}

/**
 * 批量处理多条影片名称
 *
 * @param array $videoNames ['流浪地球', '盗梦空间', ...]
 * @return array
 */
function deepseek_fetch_video_batch($videoNames)
{
    if(!is_array($videoNames)) return array();
    $out = array();
    foreach($videoNames as $i=>$name)
    {
        $out[$i] = deepseek_fetch_video($name);
        usleep(200000);
    }
    return $out;
}

/**
 * AI 影片推荐：根据访客需求，从本站候选片单中挑选影片
 *
 * @param string $question   访客输入的需求，如“推荐几部科幻片”
 * @param array  $candidates 候选片单，每项含 id/name/year/area/genre/douban/note/director/actor
 * @param array  $history    最近几轮对话 [['role'=>'user'|'assistant','content'=>'...'], ...]
 * @param string $siteName   网站名称
 * @param array  $hotTitles  近期热门片名，仅作推荐风格参考
 * @return array ['ok'=>true,'reply'=>'...','movies'=>[['id'=>..,'reason'=>'..'], ...]]
 */
function deepseek_recommend_movies($question, $candidates, $history = array(), $siteName = '', $hotTitles = array())
{
    $question = trim($question);
    if($question === '')
    {
        return array('ok'=>false, 'error'=>'请输入您想看的内容');
    }
    if(!is_array($candidates) || count($candidates) === 0)
    {
        return array('ok'=>true, 'reply'=>'片库里暂时没有找到合适的影片，换个说法再试试吧。', 'movies'=>array());
    }

    $siteName = trim($siteName) !== '' ? trim($siteName) : '本站';

    $systemPrompt = <<<SYS
你是「{$siteName}」网站的 AI 影片推荐官，负责根据访客的喜好从系统提供的候选片单中挑选影片。

规则：
1. 只能推荐候选片单中真实存在的影片，严禁编造片单之外的片名或 ID。
2. 综合考虑类型、地区、年份、导演、演员、心情和观看场景，挑选最匹配的影片。
3. 推荐 3-5 部；如果候选片单里确实没有合适的，就诚实说明，movies 返回空数组。
4. reply 使用自然、热情的中文口语，60-140 字，简单说明推荐思路；每次的措辞、开头和推荐词都要随机多变，避免固定套话；不要分点、不要 Markdown、不要表情符号。
5. 每部影片的 reason 用 15-35 字说明为什么推荐给这位访客。

只输出严格 JSON（不要代码块、不要多余文字）：
{"reply":"整体推荐语","movies":[{"id":123,"reason":"推荐理由"}]}
SYS;

    // 组装候选片单文本
    $lines = array();
    foreach($candidates as $c)
    {
        $id = intval(isset($c['id']) ? $c['id'] : 0);
        $name = trim(isset($c['name']) ? $c['name'] : '');
        if($id <= 0 || $name === '') continue;

        $year   = intval(isset($c['year']) ? $c['year'] : 0);
        $area   = trim(isset($c['area']) ? $c['area'] : '');
        $genre  = trim(isset($c['genre']) ? $c['genre'] : '');
        $note   = trim(isset($c['note']) ? $c['note'] : '');
        $douban = floatval(isset($c['douban']) ? $c['douban'] : 0);
        $people = trim((isset($c['director']) ? $c['director'] : '').','.(isset($c['actor']) ? $c['actor'] : ''), ',');

        $lines[] = $id.'|'.mb_substr($name, 0, 40, 'UTF-8')
            .'|'.($year > 0 ? $year : '')
            .'|'.mb_substr($area, 0, 20, 'UTF-8')
            .'|'.mb_substr($genre, 0, 30, 'UTF-8')
            .'|'.($douban > 0 ? $douban : '')
            .'|'.mb_substr($note, 0, 16, 'UTF-8')
            .'|'.mb_substr($people, 0, 24, 'UTF-8');
    }

    $messages = array(
        array('role' => 'system', 'content' => $systemPrompt),
    );

    // 最近对话上下文（最多 6 条）
    if(is_array($history))
    {
        $history = array_slice($history, -6);
        foreach($history as $h)
        {
            if(!is_array($h)) continue;
            $role = isset($h['role']) ? $h['role'] : '';
            $content = isset($h['content']) ? trim($h['content']) : '';
            if(($role === 'user' || $role === 'assistant') && $content !== '')
            {
                $messages[] = array('role' => $role, 'content' => mb_substr($content, 0, 300, 'UTF-8'));
            }
        }
    }

    $hotText = '';
    if(is_array($hotTitles) && count($hotTitles) > 0)
    {
        $names = array();
        foreach(array_slice($hotTitles, 0, 8) as $t)
        {
            $t = trim((string)$t);
            if($t !== '') $names[] = $t;
        }
        if($names)
        {
            $hotText = "\n平台近期热门参考（仅作风格参考，严禁推荐候选片单之外的作品）：".implode('、', $names)."\n";
        }
    }

    $messages[] = array(
        'role' => 'user',
        'content' => "访客需求：{$question}\n{$hotText}\n候选片单（id|片名|年份|地区|类型|豆瓣|备注|导演演员）：\n".implode("\n", $lines)."\n\n请根据以上候选片单，严格按 JSON 格式输出。",
    );

    $result = deepseek_chat($messages, 0.85, true);
    if(!$result['ok']) return $result;

    $raw = trim($result['content']);
    $jsonStr = deepseek_extract_json($raw);
    $data = $jsonStr !== null ? json_decode($jsonStr, true) : null;

    // 模型未按 JSON 返回：把原文作为推荐语兜底
    if(!is_array($data))
    {
        $fallback = trim(preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $raw));
        return array('ok'=>true, 'reply'=>mb_substr($fallback, 0, 600, 'UTF-8'), 'movies'=>array());
    }

    $reply = isset($data['reply']) && is_string($data['reply']) ? trim($data['reply']) : '';
    if($reply === '') $reply = '根据你的需求，为你挑选了这几部影片：';

    $valid = array();
    foreach($candidates as $c)
    {
        if(isset($c['id'])) $valid[intval($c['id'])] = $c;
    }

    $movies = array();
    $used = array();
    $list = isset($data['movies']) && is_array($data['movies']) ? $data['movies'] : array();
    foreach($list as $m)
    {
        if(!is_array($m)) continue;
        $id = intval(isset($m['id']) ? $m['id'] : 0);
        if($id <= 0 || !isset($valid[$id]) || isset($used[$id])) continue;
        $used[$id] = 1;
        $item = $valid[$id];
        $item['reason'] = isset($m['reason']) && is_string($m['reason'])
            ? mb_substr(trim($m['reason']), 0, 80, 'UTF-8')
            : '';
        $movies[] = $item;
        if(count($movies) >= 5) break;
    }

    return array('ok'=>true, 'reply'=>mb_substr($reply, 0, 600, 'UTF-8'), 'movies'=>$movies);
}

/**
 * AI 根据访客需求给出匹配片名（站内关键词无结果时兜底）
 *
 * @param string $question 访客需求
 * @param string $siteName 网站名称
 * @param int    $count    期望数量
 * @return array ['ok'=>true,'titles'=>['片名1','片名2',...]]
 */
function deepseek_suggest_titles($question, $siteName = '', $count = 10)
{
    $count = max(5, min(15, intval($count)));
    $siteName = trim($siteName) !== '' ? trim($siteName) : '本站';

    $messages = array(
        array(
            'role' => 'system',
            'content' => "你是「{$siteName}」的影视推荐助手。根据访客需求列出最匹配的影视作品片名，只输出严格 JSON，不要代码块，不要多余文字。",
        ),
        array(
            'role' => 'user',
            'content' => "访客需求：{$question}\n请列出 {$count} 部最匹配的影视作品片名，电影和剧集均可。\n"
                ."输出格式：{\"titles\":[\"片名1\",\"片名2\"]}\n片名不要加书名号。",
        ),
    );

    $result = deepseek_chat($messages, 0.5, true);
    if(!$result['ok']) return $result;

    $jsonStr = deepseek_extract_json(trim($result['content']));
    $data = $jsonStr !== null ? json_decode($jsonStr, true) : null;
    if(!is_array($data) || !isset($data['titles']) || !is_array($data['titles']))
    {
        return array('ok'=>false, 'error'=>'AI 返回的片名解析失败');
    }

    $titles = array();
    foreach($data['titles'] as $t)
    {
        if(!is_string($t)) continue;

        $t = trim($t, "《》“”\"' ");
        if($t === '' || mb_strlen($t, 'UTF-8') > 40) continue;
        if(!in_array($t, $titles)) $titles[] = $t;
        if(count($titles) >= $count) break;
    }

    if(count($titles) === 0)
    {
        return array('ok'=>false, 'error'=>'AI 未返回片名');
    }

    return array('ok'=>true, 'titles'=>$titles);
}

/**
 * AI 获取近期热门影视资源
 *
 * @param string $siteName 网站名称
 * @param int    $count    期望数量
 * @return array ['ok'=>true,'items'=>[['title'=>'..','type'=>'..','reason'=>'..'], ...]]
 */
function deepseek_hot_movies($siteName = '', $count = 12)
{
    $count = max(6, min(20, intval($count)));
    $siteName = trim($siteName) !== '' ? trim($siteName) : '本站';

    $messages = array(
        array(
            'role' => 'system',
            'content' => "你是「{$siteName}」的影视趋势助手，负责整理近期热度较高的电影、剧集和动画。只输出严格 JSON，不要代码块，不要多余文字。",
        ),
        array(
            'role' => 'user',
            'content' => "请列出最近一两年（尽量接近当前时间）讨论度和热度较高的影视作品 {$count} 部，覆盖电影、剧集、动画，避免重复。\n"
                ."输出格式：{\"items\":[{\"title\":\"片名\",\"type\":\"电影/剧集/动画\",\"reason\":\"一句话热度说明\"}]}\n"
                ."片名不要加书名号，reason 不超过 30 字。",
        ),
    );

    $result = deepseek_chat($messages, 0.6, true);
    if(!$result['ok']) return $result;

    $jsonStr = deepseek_extract_json(trim($result['content']));
    $data = $jsonStr !== null ? json_decode($jsonStr, true) : null;
    if(!is_array($data) || !isset($data['items']) || !is_array($data['items']))
    {
        return array('ok'=>false, 'error'=>'AI 返回的热门影视内容解析失败');
    }

    $types = array('电影', '剧集', '电视剧', '动画', '综艺', '纪录片');
    $items = array();
    $seen = array();
    foreach($data['items'] as $it)
    {
        if(!is_array($it)) continue;

        $title = isset($it['title']) ? trim($it['title']) : '';
        $title = trim($title, "《》“”\"' ");
        if($title === '' || mb_strlen($title, 'UTF-8') > 40 || isset($seen[$title])) continue;
        $seen[$title] = 1;

        $type = isset($it['type']) ? trim($it['type']) : '影视';
        if(!in_array($type, $types)) $type = '影视';
        $reason = isset($it['reason']) ? mb_substr(trim($it['reason']), 0, 50, 'UTF-8') : '';

        $items[] = array('title'=>$title, 'type'=>$type, 'reason'=>$reason);
        if(count($items) >= $count) break;
    }

    if(count($items) === 0)
    {
        return array('ok'=>false, 'error'=>'AI 未返回热门影视资源');
    }

    return array('ok'=>true, 'items'=>$items);
}
