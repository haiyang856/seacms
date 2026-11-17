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
    global $ai_open, $ai_provider, $ai_api_url, $ai_api_key, $ai_model, $ai_temperature, $ai_max_tokens, $ai_search_correct;
    if(isset($ai_open))            $cfg['open']            = $ai_open;
    if(isset($ai_provider))        $cfg['provider']        = $ai_provider;
    if(isset($ai_api_url))         $cfg['api_url']         = $ai_api_url;
    if(isset($ai_api_key))         $cfg['api_key']         = $ai_api_key;
    if(isset($ai_model))           $cfg['model']           = $ai_model;
    if(isset($ai_temperature))     $cfg['temperature']     = $ai_temperature;
    if(isset($ai_max_tokens))      $cfg['max_tokens']      = $ai_max_tokens;
    if(isset($ai_search_correct))  $cfg['ai_search_correct'] = $ai_search_correct;

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
