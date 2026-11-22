<?php
/**
 * 后台 AI 悬浮助手（可复用片段）
 * 仅在 AI 已开启且配置了 API Key 时输出；不依赖 sea_INC 运行环境
 * 调用前可设置 $ai_widget_endpoint 指定相对当前页面的接口地址
 */
$aiWidgetCfgFile = dirname(__FILE__) . '/../data/admin/ai.php';
if (!is_file($aiWidgetCfgFile)) {
    $aiWidgetCfgFile = dirname(__FILE__) . '/../data/admin/deepseek.php';
}
$ai_widget_open = 'off';
$ai_widget_key = '';
if (is_file($aiWidgetCfgFile)) {
    include $aiWidgetCfgFile;
    if (isset($ai_open)) {
        $ai_widget_open = (string)$ai_open;
    }
    if (isset($ai_api_key)) {
        $ai_widget_key = (string)$ai_api_key;
    }
    if ($ai_widget_open !== 'on' && isset($deepseek_open)) {
        $ai_widget_open = (string)$deepseek_open;
    }
    if ($ai_widget_key === '' && isset($deepseek_api_key)) {
        $ai_widget_key = (string)$deepseek_api_key;
    }
}
if ($ai_widget_open !== 'on' || $ai_widget_key === '') {
    return;
}
unset($aiWidgetCfgFile, $ai_widget_open, $ai_widget_key);
if (!isset($ai_widget_endpoint) || $ai_widget_endpoint === '') {
    $ai_widget_endpoint = 'admin_video.php?action=ai_help';
}
?>
<style>
#ai_help_btn {
    position: fixed; right: 20px; bottom: 20px; z-index: 9999;
    background: linear-gradient(135deg, #059669, #10b981);
    color: #fff; border: 0; border-radius: 24px; padding: 10px 18px;
    font-size: 13px; cursor: pointer; box-shadow: 0 4px 12px rgba(16,185,129,0.4);
    display: inline-flex; align-items: center; gap: 6px; font-family: inherit;
    transition: transform 0.15s;
}
#ai_help_btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(16,185,129,0.5); }
#ai_help_btn svg { flex-shrink: 0; }
#ai_help_mask {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 10001;
    display: none;
}
#ai_help_panel {
    position: fixed; right: 20px; bottom: 70px; width: 420px; height: 520px;
    z-index: 10002; display: none; flex-direction: column;
    background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    overflow: hidden;
}
#ai_help_panel.show, #ai_help_mask.show { display: flex; }
#ai_help_panel.show { display: flex; }
#ai_help_head {
    padding: 12px 16px; background: linear-gradient(135deg, #059669, #10b981);
    color: #fff; display: flex; justify-content: space-between; align-items: center;
}
#ai_help_head b { font-size: 14px; }
#ai_help_head button {
    background: transparent; border: 0; color: #fff; font-size: 18px; cursor: pointer;
    line-height: 1;
}
#ai_help_body {
    flex: 1; overflow-y: auto; padding: 14px; background: #f9fafb;
    font-size: 13px; line-height: 1.7;
}
.ai_msg { margin-bottom: 12px; text-align: left; }
.ai_msg.user { text-align: right; }
.ai_msg .bubble {
    display: inline-block; padding: 8px 12px; border-radius: 10px;
    max-width: 85%; word-break: break-word; white-space: pre-wrap; text-align: left;
}
.ai_msg.user .bubble { background: #4f46e5; color: #fff; text-align: left; }
.ai_msg.ai .bubble { background: #fff; border: 1px solid #e5e7eb; color: #111827; }
.ai_msg.loading .bubble { color: #6b7280; font-style: italic; }
#ai_help_input {
    border-top: 1px solid #e5e7eb; padding: 10px; display: flex; gap: 8px;
    background: #fff;
}
#ai_help_input textarea {
    flex: 1; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px;
    resize: none; height: 52px; font-size: 13px; font-family: inherit;
    outline: none;
}
#ai_help_input textarea:focus { border-color: #10b981; }
#ai_help_input button {
    background: linear-gradient(135deg, #059669, #10b981); color: #fff; border: 0;
    border-radius: 6px; padding: 0 16px; cursor: pointer; font-size: 13px;
}
#ai_help_input button:disabled { opacity: 0.6; cursor: not-allowed; }
</style>

<button id="ai_help_btn" onclick="aiHelpToggle()">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Ai
</button>

<div id="ai_help_mask" onclick="aiHelpClose()"></div>
<div id="ai_help_panel">
    <div id="ai_help_head">
        <b>🤖 海洋 CMS AI 助手</b>
        <button onclick="aiHelpClose()">×</button>
    </div>
    <div id="ai_help_body">
    </div>
    <div id="ai_help_input">
        <textarea id="ai_q" placeholder="输入您的海洋 CMS 问题... (Enter 发送, Shift+Enter 换行)" onkeydown="aiHelpKey(event)"></textarea>
        <button id="ai_send" onclick="aiHelpSend()">发送</button>
    </div>
</div>

<script type="text/javascript">
var AI_HELP_ENDPOINT = <?php echo json_encode($ai_widget_endpoint); ?>;
function aiHelpToggle() {
    var body = document.getElementById('ai_help_body');
    if(!body.dataset.init) {
        aiHelpAppend('ai', '您好！我是海洋 CMS AI 助手。\n\n可以帮您：\n• 解答后台操作问题\n• 排查功能配置错误\n• 给出模板标签/代码示例\n• 解释数据库表结构和字段含义\n\n请直接输入您的问题 😊');
        body.dataset.init = '1';
    }
    document.getElementById('ai_help_mask').classList.toggle('show');
    document.getElementById('ai_help_panel').classList.toggle('show');
    setTimeout(function(){ document.getElementById('ai_q').focus(); }, 100);
}
function aiHelpClose() {
    document.getElementById('ai_help_mask').classList.remove('show');
    document.getElementById('ai_help_panel').classList.remove('show');
}
function aiHelpKey(e) {
    if(e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); aiHelpSend(); }
}
function aiHelpExtractContext() {
    var ctx = [];
    var title = document.querySelector('title');
    if(title && title.textContent) ctx.push('页面标题: ' + title.textContent.trim());
    var path = location.pathname.split('/').pop() + location.search;
    ctx.push('当前页面: ' + path);
    var names = [];
    document.querySelectorAll('input[name], select[name], textarea[name]').forEach(function(el){
        var n = el.getAttribute('name');
        if(n && n.length < 40 && names.indexOf(n) === -1) names.push(n);
    });
    if(names.length) ctx.push('主要表单字段: ' + names.join(', '));
    var heads = [];
    document.querySelectorAll('.td_title, .thead td').forEach(function(el){
        var t = el.textContent.trim();
        if(t && t.length < 30 && heads.indexOf(t) === -1) heads.push(t);
    });
    if(heads.length) ctx.push('页面分区: ' + heads.join(' | '));
    return ctx.join('\n');
}
function aiHelpAppend(type, text) {
    var body = document.getElementById('ai_help_body');
    var div = document.createElement('div');
    div.className = 'ai_msg ' + type;
    var bub = document.createElement('div');
    bub.className = 'bubble';
    bub.textContent = text;
    bub.style.whiteSpace = 'pre-wrap';
    bub.style.wordBreak = 'break-word';
    div.appendChild(bub);
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
    return div;
}
function aiHelpSend() {
    var q = document.getElementById('ai_q');
    var btn = document.getElementById('ai_send');
    var text = q.value.trim();
    if(!text) return;

    aiHelpAppend('user', text);
    q.value = '';
    btn.disabled = true; btn.textContent = '发送中...';

    var loadingDiv = aiHelpAppend('ai loading', '思考中，请稍候...');

    var context = aiHelpExtractContext();
    var opts = {
        method: 'post',
        parameters: { 'question': text, 'context': context },
        onSuccess: function(transport) {
            var resp;
            try { resp = eval('(' + transport.responseText + ')'); }
            catch(e) { resp = {ok:false, error:'响应解析失败'}; }
            loadingDiv.remove();
            if(!resp.ok) {
                aiHelpAppend('ai', '❌ ' + (resp.error || '未知错误'));
            } else {
                aiHelpAppend('ai', resp.answer);
            }
        },
        onFailure: function() {
            loadingDiv.remove();
            aiHelpAppend('ai', '❌ 网络请求失败，请检查 DeepSeek 接口是否正常');
        },
        onComplete: function() {
            btn.disabled = false; btn.textContent = '发送';
            q.focus();
        }
    };
    if(typeof Ajax !== 'undefined' && Ajax.Request) {
        new Ajax.Request(AI_HELP_ENDPOINT, opts);
    } else {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', AI_HELP_ENDPOINT, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function(){ opts.onSuccess({responseText: xhr.responseText}); };
        xhr.onerror = function(){ opts.onFailure(); };
        xhr.onreadystatechange = function(){ if(xhr.readyState===4) opts.onComplete(); };
        xhr.send('question=' + encodeURIComponent(text) + '&context=' + encodeURIComponent(context));
    }
}
</script>
