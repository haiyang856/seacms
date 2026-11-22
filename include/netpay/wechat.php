<?php
/**
 * 标准微信支付：移动端优先 H5（MWEB），桌面端回退 Native 扫码（APIv2，MD5 签名）
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}
require_once(dirname(__FILE__) . '/config.php');

function netpay_wechat_sign($params, $key)
{
    unset($params['sign']);
    ksort($params, SORT_STRING);
    $parts = array();
    foreach ($params as $k => $v) {
        if ($v === null || $v === '' || is_array($v) || is_object($v)) {
            continue;
        }
        $parts[] = $k . '=' . $v;
    }
    return strtoupper(md5(implode('&', $parts) . '&key=' . $key));
}

function netpay_wechat_xml($params)
{
    $xml = '<xml>';
    foreach ($params as $k => $v) {
        $xml .= '<' . $k . '>' . netpay_xml_cdata($v) . '</' . $k . '>';
    }
    $xml .= '</xml>';
    return $xml;
}

function netpay_wechat_parse_xml($raw)
{
    if (!is_string($raw) || trim($raw) === '') {
        return false;
    }
    $prev = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if ($xml === false) {
        return false;
    }
    $data = array();
    foreach ($xml->children() as $node) {
        $data[$node->getName()] = (string)$node;
    }
    return $data;
}

function netpay_wechat_reply($success, $message = '')
{
    if ($success) {
        return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
    }
    return '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg>' . netpay_xml_cdata($message) . '</return_msg></xml>';
}

/**
 * 是否在微信内置浏览器中（需使用 JSAPI 支付）
 */
function netpay_wechat_in_wechat()
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower((string)$_SERVER['HTTP_USER_AGENT']) : '';
    return strpos($ua, 'micromessenger') !== false;
}

/**
 * 读取会话中缓存的 openid
 */
function netpay_wechat_session_openid()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    $openid = isset($_SESSION['netpay_wx_openid']) ? (string)$_SESSION['netpay_wx_openid'] : '';
    return preg_match('/^[0-9A-Za-z_\-]{10,64}$/', $openid) ? $openid : '';
}

/**
 * 微信网页授权地址（snsapi_base，仅静默获取 openid）
 */
function netpay_wechat_oauth_url($order)
{
    $config = netpay_load_config();
    $state = bin2hex(random_bytes(8));
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['netpay_wx_oauth_state'] = $state;
        $_SESSION['netpay_wx_oauth_orderno'] = (string)$order['orderno'];
    }
    $query = http_build_query(array(
        'appid'         => $config['wechat_appid'],
        'redirect_uri'  => netpay_base_url() . '/include/netpay/wechat_oauth.php',
        'response_type' => 'code',
        'scope'         => 'snsapi_base',
        'state'         => $state,
    ));
    return 'https://open.weixin.qq.com/connect/oauth2/authorize?' . $query . '#wechat_redirect';
}

/**
 * 生成 JSAPI 调起参数（paySign 使用 APIv2 MD5 签名）
 */
function netpay_wechat_jsapi_params($prepayId, $config)
{
    $params = array(
        'appId'     => $config['wechat_appid'],
        'timeStamp' => (string)time(),
        'nonceStr'  => bin2hex(random_bytes(16)),
        'package'   => 'prepay_id=' . $prepayId,
        'signType'  => 'MD5',
    );
    $params['paySign'] = netpay_wechat_sign($params, $config['wechat_key']);
    return $params;
}

/**
 * 微信内 JSAPI 支付页：自动调起收银台并轮询支付结果
 */
function netpay_wechat_jsapi_page($order, $jsParams)
{
    $queryUrl = netpay_base_url() . '/include/netpay/query.php?orderno=' . urlencode($order['orderno']);
    $returnUrl = netpay_base_url() . '/member/index.php?action=buy3&paydone=1';
    $json = json_encode($jsParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $amount = number_format(floatval($order['amount']), 2, '.', '');
    $points = intval($order['points']);
    $orderno = netpay_html($order['orderno']);
    return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>微信支付</title><style>'
        . 'body{margin:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC",sans-serif;color:#1f2937}'
        . '.np-wrap{max-width:460px;margin:40px auto;background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.08);padding:28px 24px;text-align:center}'
        . '.np-title{font-size:20px;font-weight:600;margin:0 0 6px}.np-sub{color:#6b7280;font-size:13px;margin-bottom:18px}'
        . '.np-amount{font-size:30px;font-weight:700;color:#e11d48;margin:10px 0 4px}.np-points{color:#059669;font-size:14px;margin-bottom:18px}'
        . '.np-status{margin-top:14px;color:#6b7280;font-size:13px}.np-btn{display:inline-block;margin-top:18px;padding:11px 26px;border:none;border-radius:8px;background:#07c160;color:#fff;font-size:15px;cursor:pointer}'
        . '.np-code{word-break:break-all;color:#9ca3af;font-size:11px;margin-top:12px}'
        . '</style></head><body><div class="np-wrap">'
        . '<h1 class="np-title">微信支付</h1><div class="np-sub">请在微信中完成支付</div>'
        . '<div class="np-amount">￥' . $amount . '</div>'
        . '<div class="np-points">到账积分：' . $points . '</div>'
        . '<button type="button" class="np-btn" id="npPayBtn">立即支付</button>'
        . '<div class="np-status" id="npStatus">正在调起微信支付...</div>'
        . '<a class="np-btn" style="background:#e5e7eb;color:#374151;text-decoration:none;" href="' . netpay_html($returnUrl) . '">支付完成后返回充值记录</a>'
        . '<div class="np-code">订单号：' . $orderno . '</div>'
        . '</div><script>var NP_PAY=' . $json . ';var NP_QUERY=' . json_encode($queryUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';'
        . 'var NP_RETURN=' . json_encode($returnUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';'
        . 'var npPaying=false;'
        . 'function npStatus(t){document.getElementById("npStatus").textContent=t;}'
        . 'function npPoll(){var t=0;var timer=setInterval(function(){if(++t>200){clearInterval(timer);return;}'
        . 'fetch(NP_QUERY,{credentials:"same-origin"}).then(function(r){return r.json()}).then(function(d){'
        . 'if(d&&d.status===1){clearInterval(timer);npStatus("支付成功，正在跳转...");location.href=NP_RETURN;}}).catch(function(){});},3000);}'
        . 'function npInvoke(){if(npPaying){return;}npPaying=true;npStatus("正在调起微信支付...");'
        . 'WeixinJSBridge.invoke("getBrandWCPayRequest",NP_PAY,function(res){npPaying=false;'
        . 'if(res&&res.err_msg==="get_brand_wcpay_request:ok"){npStatus("支付成功，正在跳转...");location.href=NP_RETURN;}'
        . 'else if(res&&res.err_msg==="get_brand_wcpay_request:cancel"){npStatus("已取消支付，可点击按钮重新支付");}'
        . 'else{npStatus("支付未完成，请重试或长按订单号联系客服");}});}'
        . 'function npReady(){if(typeof WeixinJSBridge==="undefined"){document.addEventListener("WeixinJSBridgeReady",npReady,false);return;}'
        . 'document.getElementById("npPayBtn").addEventListener("click",npInvoke);npInvoke();npPoll();}'
        . 'npReady();</script></body></html>';
}


/**
 * 生成微信支付：微信内 JSAPI，移动端 H5 跳转，桌面端扫码
 */
function netpay_wechat_create($order)
{
    $config = netpay_load_config();
    if ($config['wechat_enable'] !== 'on') {
        return array('ok' => false, 'error' => '微信支付未开启');
    }
    if ($config['wechat_appid'] === '' || $config['wechat_mchid'] === '' || $config['wechat_key'] === ''
        || !netpay_url_is_valid($config['wechat_gateway'])) {
        return array('ok' => false, 'error' => '微信支付配置不完整');
    }
    $inWechat = netpay_wechat_in_wechat();
    $mobile = netpay_is_mobile();
    $openid = '';
    if ($inWechat) {
        if ($config['wechat_secret'] === '') {
            return array('ok' => false, 'error' => '微信内支付需在后台填写网页授权密钥（AppSecret）');
        }
        $openid = netpay_wechat_session_openid();
        if ($openid === '') {
            // 微信内置浏览器必须先通过网页授权拿到 openid 才能使用 JSAPI
            return array('ok' => true, 'type' => 'redirect', 'url' => netpay_wechat_oauth_url($order));
        }
        // 同一订单重复发起时复用未过期的 prepay_id，避免触发商户订单号重复错误
        $cacheKey = 'netpay_wx_prepay_' . $order['orderno'];
        if (!empty($_SESSION[$cacheKey]['prepay_id']) && intval($_SESSION[$cacheKey]['time']) > time() - 6600) {
            $jsapi = netpay_wechat_jsapi_params((string)$_SESSION[$cacheKey]['prepay_id'], $config);
            return array('ok' => true, 'type' => 'html', 'html' => netpay_wechat_jsapi_page($order, $jsapi));
        }
    }
    $params = array(
        'appid'            => $config['wechat_appid'],
        'mch_id'           => $config['wechat_mchid'],
        'nonce_str'        => bin2hex(random_bytes(16)),
        'body'             => mb_substr($config['pay_subject'], 0, 60, 'UTF-8') . '-' . $order['orderno'],
        'out_trade_no'     => $order['orderno'],
        'total_fee'        => netpay_amount_cent($order['amount']),
        'spbill_create_ip' => netpay_client_ip(),
        'notify_url'       => netpay_notify_url('wechat'),
        'trade_type'       => $inWechat ? 'JSAPI' : ($mobile ? 'MWEB' : 'NATIVE'),
        'time_expire'      => date('YmdHis', time() + max(60, intval($config['pay_order_expire']))),
    );
    if ($inWechat) {
        $params['openid'] = $openid;
    } elseif ($mobile) {
        // H5 支付场景信息，wap_url 必须与商户平台配置的 H5 支付域名一致
        $sceneInfo = array(
            'h5_info' => array(
                'type'     => 'Wap',
                'wap_url'  => netpay_base_url(),
                'wap_name' => mb_substr($config['pay_subject'], 0, 60, 'UTF-8'),
            ),
        );
        $params['scene_info'] = json_encode($sceneInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $params['product_id'] = $order['orderno'];
    }
    $params['sign'] = netpay_wechat_sign($params, $config['wechat_key']);
    $resp = netpay_http_request(
        $config['wechat_gateway'],
        netpay_wechat_xml($params),
        array('Content-Type: text/xml; charset=utf-8'),
        20
    );
    if (!$resp['ok']) {
        netpay_log('微信下单请求失败', array('orderno' => $order['orderno'], 'error' => $resp['error']));
        return array('ok' => false, 'error' => '微信支付下单请求失败：' . $resp['error']);
    }
    $data = netpay_wechat_parse_xml($resp['body']);
    if (!is_array($data)) {
        netpay_log('微信下单返回解析失败', array('orderno' => $order['orderno']));
        return array('ok' => false, 'error' => '微信支付返回数据异常');
    }
    if (empty($data['return_code']) || $data['return_code'] !== 'SUCCESS') {
        return array('ok' => false, 'error' => '微信支付下单失败：' . (isset($data['return_msg']) ? $data['return_msg'] : '未知错误'));
    }
    if (empty($data['result_code']) || $data['result_code'] !== 'SUCCESS') {
        return array('ok' => false, 'error' => '微信支付下单失败：' . (isset($data['err_code_des']) ? $data['err_code_des'] : '未知错误'));
    }
    if ($inWechat) {
        if (empty($data['prepay_id'])) {
            return array('ok' => false, 'error' => '微信支付未返回预支付标识');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['netpay_wx_prepay_' . $order['orderno']] = array('prepay_id' => (string)$data['prepay_id'], 'time' => time());
        }
        $jsapi = netpay_wechat_jsapi_params((string)$data['prepay_id'], $config);
        return array('ok' => true, 'type' => 'html', 'html' => netpay_wechat_jsapi_page($order, $jsapi));
    }
    if ($mobile) {
        if (empty($data['mweb_url']) || !netpay_url_is_valid($data['mweb_url'])) {
            return array('ok' => false, 'error' => '微信支付未返回 H5 支付地址');
        }
        $url = (string)$data['mweb_url'];
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'redirect_url=' . urlencode(netpay_return_url('wechat'));
        return array('ok' => true, 'type' => 'redirect', 'url' => $url);
    }
    if (empty($data['code_url'])) {
        return array('ok' => false, 'error' => '微信支付未返回二维码地址');
    }
    return array('ok' => true, 'type' => 'wechat_qr', 'code_url' => (string)$data['code_url'], 'html' => netpay_wechat_qr_page($order, (string)$data['code_url']));
}

function netpay_wechat_qr_page($order, $codeUrl)
{
    $qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=' . urlencode($codeUrl);
    $queryUrl = netpay_base_url() . '/include/netpay/query.php?orderno=' . urlencode($order['orderno']);
    $returnUrl = netpay_base_url() . '/member/index.php?action=buy3&paydone=1';
    $amount = number_format(floatval($order['amount']), 2, '.', '');
    $points = intval($order['points']);
    $orderno = netpay_html($order['orderno']);
    $codeUrlEsc = netpay_html($codeUrl);
    $qrImgEsc = netpay_html($qrImg);
    $queryEsc = netpay_html($queryUrl);
    $returnEsc = netpay_html($returnUrl);
    return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>微信扫码支付</title><style>'
        . 'body{margin:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC",sans-serif;color:#1f2937}'
        . '.np-wrap{max-width:460px;margin:40px auto;background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.08);padding:28px 24px;text-align:center}'
        . '.np-title{font-size:20px;font-weight:600;margin:0 0 6px}.np-sub{color:#6b7280;font-size:13px;margin-bottom:18px}'
        . '.np-amount{font-size:30px;font-weight:700;color:#e11d48;margin:10px 0 4px}.np-points{color:#059669;font-size:14px;margin-bottom:18px}'
        . '.np-qr{width:240px;height:240px;border:1px solid #e5e7eb;border-radius:10px;padding:8px;background:#fff}'
        . '.np-code{word-break:break-all;color:#9ca3af;font-size:11px;margin-top:12px}'
        . '.np-link{display:inline-block;margin-top:18px;color:#2563eb;text-decoration:none;font-size:14px}'
        . '.np-status{margin-top:14px;color:#6b7280;font-size:13px}'
        . '</style></head><body><div class="np-wrap">'
        . '<h1 class="np-title">微信扫码支付</h1><div class="np-sub">请使用微信扫一扫完成支付</div>'
        . '<div class="np-amount">￥' . $amount . '</div>'
        . '<div class="np-points">到账积分：' . $points . '</div>'
        . '<img class="np-qr" src="' . $qrImgEsc . '" alt="微信支付二维码">'
        . '<div class="np-code">' . $codeUrlEsc . '</div>'
        . '<div class="np-status" id="npStatus">等待支付结果...</div>'
        . '<a class="np-link" href="' . $returnEsc . '">支付完成后返回充值记录</a>'
        . '<div class="np-code">订单号：' . $orderno . '</div>'
        . '</div><script>(function(){var t=0;var timer=setInterval(function(){'
        . 'if(++t>200){clearInterval(timer);return;}'
        . 'fetch("' . $queryEsc . '",{credentials:"same-origin"}).then(function(r){return r.json()}).then(function(d){'
        . 'if(d&&d.status===1){clearInterval(timer);document.getElementById("npStatus").textContent="支付成功，正在跳转...";location.href="' . $returnEsc . '";}'
        . '}).catch(function(){});},3000);})();</script></body></html>';
}

/**
 * 校验微信异步通知
 */
function netpay_wechat_verify_notify($raw)
{
    $config = netpay_load_config();
    $data = netpay_wechat_parse_xml($raw);
    if (!is_array($data) || empty($data['sign']) || empty($data['out_trade_no'])) {
        return array('ok' => false, 'error' => '通知数据异常');
    }
    if (empty($config['wechat_key'])) {
        return array('ok' => false, 'error' => '微信支付密钥未配置');
    }
    $sign = (string)$data['sign'];
    $calc = netpay_wechat_sign($data, $config['wechat_key']);
    if (!hash_equals($calc, strtoupper($sign))) {
        return array('ok' => false, 'error' => '微信签名验证失败');
    }
    if (empty($data['return_code']) || $data['return_code'] !== 'SUCCESS') {
        return array('ok' => false, 'error' => '微信通知状态异常');
    }
    if (isset($data['appid']) && $config['wechat_appid'] !== '' && !hash_equals($config['wechat_appid'], $data['appid'])) {
        return array('ok' => false, 'error' => '微信 APPID 不匹配');
    }
    if (isset($data['mch_id']) && $config['wechat_mchid'] !== '' && !hash_equals($config['wechat_mchid'], $data['mch_id'])) {
        return array('ok' => false, 'error' => '微信商户号不匹配');
    }
    if (empty($data['result_code']) || $data['result_code'] !== 'SUCCESS') {
        return array('ok' => false, 'error' => '微信交易未成功', 'ignore' => true);
    }
    return array(
        'ok'       => true,
        'orderno'  => (string)$data['out_trade_no'],
        'trade_no' => isset($data['transaction_id']) ? (string)$data['transaction_id'] : '',
        'amount'   => isset($data['total_fee']) ? intval($data['total_fee']) / 100 : 0,
    );
}
