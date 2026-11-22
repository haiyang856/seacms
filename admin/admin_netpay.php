<?php
header('Content-Type:text/html;charset=utf-8');
require_once(dirname(__FILE__) . "/config.php");
require_once(sea_INC . "/netpay/config.php");
CheckPurview();

$errors = array();
$config = netpay_load_config(true);
$saved = false;

if ($action === 'save') {
    admin_csrf_check();
    $input = $_POST;
    unset($input['csrf_token'], $input['action']);
    foreach (array_keys(netpay_mazfu_type_map()) as $type) {
        $key = 'mazfu_' . $type;
        $input[$key] = isset($_POST[$key]) ? 'on' : 'off';
    }
    $result = netpay_validate_config($input);
    $config = $result['config'];
    if (!$result['ok']) {
        $errors = $result['errors'];
    } else {
        list($ok, $msg) = netpay_save_config($config);
        if (!$ok) {
            $errors[] = $msg;
        } else {
            netpay_load_config(true);
            ShowMsg("充值接口配置已保存，配置实时生效！", "admin_netpay.php");
            exit;
        }
    }
}

function netpay_admin_input($name, $value, $type = 'text', $attrs = '')
{
    $classAttr = strpos($attrs, 'class=') === false ? ' class="np-input"' : '';
    return '<input type="' . $type . '" name="' . netpay_html($name) . '" value="' . netpay_html($value) . '"' . $classAttr . ' ' . $attrs . ' />';
}

function netpay_admin_switch($name, $value)
{
    $on = $value === 'on';
    return '<div class="np-seg">'
        . '<label class="np-seg-on"><input type="radio" name="' . netpay_html($name) . '" value="on"' . ($on ? ' checked' : '') . ' /><span>开启</span></label>'
        . '<label class="np-seg-off"><input type="radio" name="' . netpay_html($name) . '" value="off"' . (!$on ? ' checked' : '') . ' /><span>关闭</span></label>'
        . '</div>';
}

function netpay_admin_secret($name, $value, $placeholder = '')
{
    $id = 'np-sec-' . preg_replace('/[^a-z0-9_]/i', '', $name);
    return '<div class="np-secret">'
        . '<input type="password" id="' . $id . '" name="' . netpay_html($name) . '" value="' . netpay_html($value) . '" class="np-input" autocomplete="new-password" placeholder="' . netpay_html($placeholder) . '" />'
        . '<button type="button" class="np-eye" data-target="' . $id . '">显示</button>'
        . '</div>';
}

function netpay_admin_copy($value)
{
    return '<div class="np-copybox">'
        . '<input type="text" readonly class="np-input np-mono" value="' . netpay_html($value) . '" />'
        . '<button type="button" class="np-copy" data-copy="' . netpay_html($value) . '">复制</button>'
        . '</div>';
}

$configFile = netpay_config_file();
$configWritable = (is_file($configFile) && is_writable($configFile)) || (!is_file($configFile) && is_writable(dirname($configFile)));
$enabledCount = 0;
foreach (array('alipay', 'wechat', 'mazfu', 'usdt') as $channelKey) {
    if (isset($config[$channelKey . '_enable']) && $config[$channelKey . '_enable'] === 'on') {
        $enabledCount++;
    }
}
$baseUrl = netpay_base_url();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>充值接口配置</title>
<link href="img/style.css" rel="stylesheet" type="text/css" />
<script src="js/common.js" type="text/javascript"></script>
<script src="js/main.js" type="text/javascript"></script>
<style type="text/css">
.np-page{max-width:1080px;margin:0 auto;padding:16px 18px 28px;font-size:13px;color:#303133;}
.np-page *{box-sizing:border-box;}
.np-meta{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:0 0 16px;}
.np-meta-item{background:#fff;border:1px solid #e6e9ef;border-radius:8px;padding:11px 14px;min-height:62px;}
.np-meta-item span{display:block;font-size:11px;color:#8a94a6;letter-spacing:.5px;margin-bottom:5px;}
.np-meta-item code{font-family:Menlo,Consolas,monospace;font-size:12px;color:#2b3a5e;word-break:break-all;}
.np-meta-item em{font-style:normal;font-size:13px;font-weight:600;}
.np-ok{color:#0a7d32;}
.np-bad{color:#c62828;}
.np-btn{display:inline-block;height:34px;line-height:32px;padding:0 16px;border-radius:6px;border:1px solid transparent;font-size:13px;cursor:pointer;text-decoration:none;transition:all .2s;font-family:inherit;}
.np-btn-primary{background:#3b82f6;border-color:#3b82f6;color:#fff;box-shadow:0 3px 10px rgba(59,130,246,.35);}
.np-btn-primary:hover{background:#2f6fe0;border-color:#2f6fe0;color:#fff;}
.np-tabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px;padding:5px;background:#eef1f6;border-radius:8px;}
.np-tabs a{padding:6px 14px;border-radius:6px;color:#5b6475;text-decoration:none;font-size:12px;transition:all .2s;}
.np-tabs a:hover{background:#fff;color:#2563eb;}
.np-tabs a.active{background:#fff;color:#2563eb;font-weight:600;box-shadow:0 1px 4px rgba(0,0,0,.08);}
.np-card{background:#fff;border:1px solid #e6e9ef;border-radius:10px;margin-bottom:16px;box-shadow:0 1px 3px rgba(16,24,40,.04);overflow:hidden;scroll-margin-top:12px;}
.np-card-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:13px 18px;border-bottom:1px solid #eef1f6;background:#fbfcfe;}
.np-card-title{display:flex;align-items:center;gap:11px;}
.np-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#fff;font-weight:600;flex-shrink:0;}
.np-ico-pricing{background:linear-gradient(135deg,#f59e0b,#f97316);}
.np-ico-alipay{background:linear-gradient(135deg,#1677ff,#3b9cff);}
.np-ico-wechat{background:linear-gradient(135deg,#07c160,#3ddc84);}
.np-ico-mazfu{background:linear-gradient(135deg,#7c3aed,#a78bfa);}
.np-ico-usdt{background:linear-gradient(135deg,#26a17b,#4fd1a5);}
.np-ico-common{background:linear-gradient(135deg,#64748b,#94a3b8);}
.np-card-title h2{margin:0;font-size:14px;font-weight:600;color:#1f2a44;}
.np-card-title p{margin:3px 0 0;font-size:11px;color:#8a94a6;}
.np-card-body{padding:4px 18px 14px;}
.np-field{display:grid;grid-template-columns:150px 1fr;gap:12px;align-items:start;padding:12px 0;border-bottom:1px dashed #eef1f6;}
.np-field:last-child{border-bottom:none;padding-bottom:4px;}
.np-field > label{padding-top:8px;text-align:right;color:#5b6475;font-size:12px;}
.np-control{min-width:0;}
.np-input{width:100%;max-width:460px;height:34px;line-height:32px;padding:0 10px;border:1px solid #d8dee8;border-radius:6px;background:#fff;font-size:13px;color:#303133;font-family:inherit;transition:border-color .2s,box-shadow .2s;}
.np-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.14);}
.np-input[readonly]{background:#f6f8fb;color:#5b6475;}
.np-input.np-small{max-width:130px;}
.np-input.np-mono{font-family:Menlo,Consolas,monospace;font-size:12px;}
.np-inline{display:flex;align-items:center;gap:8px;flex-wrap:wrap;color:#5b6475;}
.np-inline .np-input{width:130px;}
.np-textarea{width:100%;max-width:560px;height:92px;padding:8px 10px;border:1px solid #d8dee8;border-radius:6px;font-family:Menlo,Consolas,monospace;font-size:12px;line-height:18px;resize:vertical;color:#303133;}
.np-textarea:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.14);}
.np-note{margin-top:5px;font-size:11px;line-height:18px;color:#8a94a6;}
.np-note code{font-family:Menlo,Consolas,monospace;color:#5b6475;}
.np-seg{display:inline-flex;padding:3px;background:#eef1f6;border-radius:7px;}
.np-seg label{cursor:pointer;position:relative;}
.np-seg input{position:absolute;opacity:0;width:0;height:0;}
.np-seg span{display:block;padding:5px 16px;border-radius:5px;font-size:12px;color:#6b7280;transition:all .18s;}
.np-seg-on input:checked + span{background:#e7f8ee;color:#0a7d32;box-shadow:inset 0 0 0 1px #b7ebc8;font-weight:600;}
.np-seg-off input:checked + span{background:#fff;color:#c62828;box-shadow:inset 0 0 0 1px #f0c4c4;font-weight:600;}
.np-secret,.np-copybox{display:flex;gap:8px;max-width:460px;}
.np-secret .np-input,.np-copybox .np-input{flex:1;min-width:0;}
.np-eye,.np-copy{height:34px;padding:0 12px;border:1px solid #d8dee8;background:#f8fafc;border-radius:6px;font-size:12px;color:#5b6475;cursor:pointer;white-space:nowrap;font-family:inherit;transition:all .2s;}
.np-eye:hover,.np-copy:hover{border-color:#3b82f6;color:#2563eb;background:#f0f6ff;}
.np-checks{display:flex;flex-wrap:wrap;gap:10px 20px;padding-top:6px;}
.np-checks label{display:flex;align-items:center;gap:6px;color:#5b6475;font-size:13px;cursor:pointer;}
.np-checks input{width:15px;height:15px;accent-color:#3b82f6;}
.np-error{background:#fff1f0;border:1px solid #ffccc7;color:#a8071a;padding:11px 16px;border-radius:8px;margin:0 0 14px;font-size:12px;line-height:22px;}
.np-error strong{display:block;margin-bottom:2px;}
.np-savebar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:18px;padding:12px 18px;background:#fff;border:1px solid #e6e9ef;border-radius:10px;}
.np-savebar span{font-size:12px;color:#8a94a6;}
.np-savebar .np-btn{height:36px;line-height:34px;}
@media (max-width:900px){
  .np-meta{grid-template-columns:repeat(2,1fr);}
}
@media (max-width:640px){
  .np-page{padding:10px 10px 20px;}
  .np-meta{grid-template-columns:1fr;}
  .np-field{grid-template-columns:1fr;gap:6px;}
  .np-field > label{text-align:left;padding-top:0;}
  .np-input,.np-textarea,.np-secret,.np-copybox{max-width:100%;}
  .np-tabs a{padding:6px 10px;}
}
</style>
</head>
<body>
<script type="text/JavaScript">if(parent && parent.$ && parent.$('admincpnav')) parent.$('admincpnav').innerHTML='后台首页&nbsp;&raquo;&nbsp;系统&nbsp;&raquo;&nbsp;充值接口配置';</script>
<div class="r_main">
<div class="r_content">
<div class="r_content_1">
<div class="np-page">

    <div class="np-meta">
        <div class="np-meta-item">
            <span>配置文件</span>
            <code>data/admin/pay.php</code>
        </div>
        <div class="np-meta-item">
            <span>写入权限</span>
            <em class="<?php echo $configWritable ? 'np-ok' : 'np-bad'; ?>"><?php echo $configWritable ? '可读写' : '不可写'; ?></em>
        </div>
        <div class="np-meta-item">
            <span>已启用渠道</span>
            <em class="<?php echo $enabledCount > 0 ? 'np-ok' : 'np-bad'; ?>"><?php echo $enabledCount; ?> / 4</em>
        </div>
        <div class="np-meta-item">
            <span>回调基础地址</span>
            <code><?php echo netpay_html($baseUrl); ?></code>
        </div>
    </div>

    <nav class="np-tabs">
        <a href="#np-pricing" class="active">积分定价</a>
        <a href="#np-alipay">支付宝</a>
        <a href="#np-wechat">微信支付</a>
        <a href="#np-mazfu">码支付</a>
        <a href="#np-usdt">USDT</a>
        <a href="#np-common">通用设置</a>
    </nav>

    <form id="netpayForm" action="admin_netpay.php?action=save" method="post" autocomplete="off">
    <?php echo admin_csrf_field(); ?>

    <?php if (!empty($errors)) { ?>
    <div class="np-error">
        <strong>保存失败，请检查以下问题：</strong>
        <?php foreach ($errors as $err) { echo '· ' . netpay_html($err) . '<br />'; } ?>
    </div>
    <?php } ?>

    <section class="np-card" id="np-pricing">
        <div class="np-card-head">
            <div class="np-card-title">
                <span class="np-ico np-ico-pricing">积</span>
                <div><h2>站内积分定价</h2><p>人民币与 USDT 分别设置兑换比例</p></div>
            </div>
        </div>
        <div class="np-card-body">
            <div class="np-field">
                <label>人民币兑换比例</label>
                <div class="np-control">
                    <div class="np-inline">1 元人民币兑换 <?php echo netpay_admin_input('pay_cny_points', $config['pay_cny_points'], 'text', 'class="np-input np-small"'); ?> 积分</div>
                    <div class="np-note">支付宝、微信、码支付按此比例计算到账积分。</div>
                </div>
            </div>
            <div class="np-field">
                <label>USDT 兑换比例</label>
                <div class="np-control">
                    <div class="np-inline">1 USDT 兑换 <?php echo netpay_admin_input('pay_usdt_points', $config['pay_usdt_points'], 'text', 'class="np-input np-small"'); ?> 积分</div>
                    <div class="np-note">USDT 支付按此比例计算到账积分，修改后新订单实时生效。</div>
                </div>
            </div>
            <div class="np-field">
                <label>单笔金额限制</label>
                <div class="np-control">
                    <div class="np-inline">
                        最低 <?php echo netpay_admin_input('pay_min_money', $config['pay_min_money'], 'text', 'class="np-input np-small"'); ?> 元/USDT
                        <span style="color:#c0c6d2;">—</span>
                        最高 <?php echo netpay_admin_input('pay_max_money', $config['pay_max_money'], 'text', 'class="np-input np-small"'); ?> 元/USDT
                    </div>
                </div>
            </div>
            <div class="np-field">
                <label>订单有效期</label>
                <div class="np-control">
                    <div class="np-inline"><?php echo netpay_admin_input('pay_order_expire', $config['pay_order_expire'], 'text', 'class="np-input np-small"'); ?> 秒</div>
                    <div class="np-note">建议 600 - 1800 秒，超时后第三方支付平台将不再接受付款。</div>
                </div>
            </div>
            <div class="np-field">
                <label>商品名称</label>
                <div class="np-control">
                    <?php echo netpay_admin_input('pay_subject', $config['pay_subject'], 'text', 'maxlength="60"'); ?>
                    <div class="np-note">显示在支付平台收银台中的订单标题。</div>
                </div>
            </div>
        </div>
    </section>

    <section class="np-card" id="np-alipay">
        <div class="np-card-head">
            <div class="np-card-title">
                <span class="np-ico np-ico-alipay">支</span>
                <div><h2>标准支付宝</h2><p>手机网站支付（H5）</p></div>
            </div>
            <?php echo netpay_admin_switch('alipay_enable', $config['alipay_enable']); ?>
        </div>
        <div class="np-card-body">
            <div class="np-field">
                <label>APPID</label>
                <div class="np-control"><?php echo netpay_admin_input('alipay_appid', $config['alipay_appid'], 'text', 'maxlength="32"'); ?></div>
            </div>
            <div class="np-field">
                <label>应用私钥</label>
                <div class="np-control">
                    <textarea name="alipay_private_key" class="np-textarea" placeholder="支持 PKCS8/PKCS1，可粘贴纯 Base64 或完整 PEM"><?php echo netpay_html($config['alipay_private_key']); ?></textarea>
                    <div class="np-note">仅保存在服务器配置文件中，不会写入日志。</div>
                </div>
            </div>
            <div class="np-field">
                <label>支付宝公钥</label>
                <div class="np-control">
                    <textarea name="alipay_public_key" class="np-textarea" placeholder="支付宝公钥或支付宝公钥证书"><?php echo netpay_html($config['alipay_public_key']); ?></textarea>
                </div>
            </div>
            <div class="np-field">
                <label>网关地址</label>
                <div class="np-control">
                    <?php echo netpay_admin_input('alipay_gateway', $config['alipay_gateway'], 'text', 'maxlength="200"'); ?>
                    <div class="np-note">正式环境：<code>https://openapi.alipay.com/gateway.do</code>，需签约「手机网站支付」并配置 H5 域名。</div>
                </div>
            </div>
            <div class="np-field">
                <label>异步通知地址</label>
                <div class="np-control">
                    <?php echo netpay_admin_copy(netpay_notify_url('alipay')); ?>
                    <div class="np-note">需在支付宝开放平台配置为公网可访问的 HTTPS 地址。</div>
                </div>
            </div>
        </div>
    </section>

    <section class="np-card" id="np-wechat">
        <div class="np-card-head">
            <div class="np-card-title">
                <span class="np-ico np-ico-wechat">微</span>
                <div><h2>标准微信支付</h2><p>移动端 H5 / 桌面端扫码</p></div>
            </div>
            <?php echo netpay_admin_switch('wechat_enable', $config['wechat_enable']); ?>
        </div>
        <div class="np-card-body">
            <div class="np-field">
                <label>APPID</label>
                <div class="np-control"><?php echo netpay_admin_input('wechat_appid', $config['wechat_appid'], 'text', 'maxlength="32"'); ?></div>
            </div>
            <div class="np-field">
                <label>商户号</label>
                <div class="np-control"><?php echo netpay_admin_input('wechat_mchid', $config['wechat_mchid'], 'text', 'maxlength="32"'); ?></div>
            </div>
            <div class="np-field">
                <label>API 密钥</label>
                <div class="np-control">
                    <?php echo netpay_admin_secret('wechat_key', $config['wechat_key'], '微信支付商户平台 APIv2 密钥（32 位）'); ?>
                    <div class="np-note">用于 MD5 签名，请确认与商户平台一致。</div>
                </div>
            </div>
            <div class="np-field">
                <label>下单地址</label>
                <div class="np-control"><?php echo netpay_admin_input('wechat_gateway', $config['wechat_gateway'], 'text', 'maxlength="200"'); ?></div>
            </div>
            <div class="np-field">
                <label>异步通知地址</label>
                <div class="np-control"><?php echo netpay_admin_copy(netpay_notify_url('wechat')); ?></div>
            </div>
        </div>
    </section>

    <section class="np-card" id="np-mazfu">
        <div class="np-card-head">
            <div class="np-card-title">
                <span class="np-ico np-ico-mazfu">码</span>
                <div><h2>码支付</h2><p>易支付协议，H5 优先</p></div>
            </div>
            <?php echo netpay_admin_switch('mazfu_enable', $config['mazfu_enable']); ?>
        </div>
        <div class="np-card-body">
            <div class="np-field">
                <label>商户 ID（pid）</label>
                <div class="np-control"><?php echo netpay_admin_input('mazfu_pid', $config['mazfu_pid'], 'text', 'maxlength="64"'); ?></div>
            </div>
            <div class="np-field">
                <label>商户密钥</label>
                <div class="np-control"><?php echo netpay_admin_secret('mazfu_key', $config['mazfu_key'], '码支付商户密钥'); ?></div>
            </div>
            <div class="np-field">
                <label>提交地址</label>
                <div class="np-control">
                    <?php echo netpay_admin_input('mazfu_gateway', $config['mazfu_gateway'], 'text', 'maxlength="200"'); ?>
                    <div class="np-note">默认：<code>https://www.mazfu.com/xpay/epay/submit.php</code>，以码支付后台提供的地址为准。</div>
                </div>
            </div>
            <div class="np-field">
                <label>支付方式</label>
                <div class="np-control">
                    <div class="np-checks">
                        <?php foreach (netpay_mazfu_type_map() as $type => $meta) {
                            $key = 'mazfu_' . $type;
                            echo '<label><input type="checkbox" name="' . netpay_html($key) . '" value="on" '
                                . (isset($config[$key]) && $config[$key] === 'on' ? 'checked' : '') . ' /> '
                                . netpay_html($meta['name']) . '</label>';
                        } ?>
                    </div>
                    <div class="np-note">未勾选的支付方式不会在前台展示；网银支付对应 <code>bank</code>，QQ 钱包对应 <code>qqpay</code>，京东对应 <code>jdpay</code>，PayPal 对应 <code>paypal</code>，订单金额均按人民币计算。</div>
                </div>
            </div>
            <div class="np-field">
                <label>异步通知地址</label>
                <div class="np-control"><?php echo netpay_admin_copy(netpay_notify_url('mazfu')); ?></div>
            </div>
        </div>
    </section>

    <section class="np-card" id="np-usdt">
        <div class="np-card-head">
            <div class="np-card-title">
                <span class="np-ico np-ico-usdt">U</span>
                <div><h2>USDT 收款</h2><p>基于 BEpusdt</p></div>
            </div>
            <?php echo netpay_admin_switch('usdt_enable', $config['usdt_enable']); ?>
        </div>
        <div class="np-card-body">
            <div class="np-field">
                <label>BEpusdt 地址</label>
                <div class="np-control">
                    <?php echo netpay_admin_input('usdt_gateway', $config['usdt_gateway'], 'text', 'placeholder="https://pay.example.com" maxlength="200"'); ?>
                    <div class="np-note">BEpusdt 服务根地址，不带末尾斜杠。</div>
                    <div class="np-note">需自行安装 BEpusdt 服务，教程：<a href="https://github.com/v03413/BEpusdt/blob/main/docs/docker/docker.md" target="_blank" rel="noopener" style="color:#1677ff;">Docker 部署文档</a></div>
                </div>
            </div>
            <div class="np-field">
                <label>通信密钥</label>
                <div class="np-control">
                    <?php echo netpay_admin_secret('usdt_token', $config['usdt_token'], 'BEpusdt API 通信密钥'); ?>
                    <div class="np-note">用于下单与回调验签，请勿泄露。</div>
                </div>
            </div>
            <div class="np-field">
                <label>交易类型</label>
                <div class="np-control">
                    <?php echo netpay_admin_input('usdt_trade_type', $config['usdt_trade_type'], 'text', 'class="np-input np-small" maxlength="32"'); ?>
                    <div class="np-note">如 usdt.trc20、usdt.erc20、usdt.bep20，需与 BEpusdt 支持的交易类型一致。</div>
                </div>
            </div>
            <div class="np-field">
                <label>异步通知地址</label>
                <div class="np-control"><?php echo netpay_admin_copy(netpay_notify_url('usdt')); ?></div>
            </div>
        </div>
    </section>

    <section class="np-card" id="np-common">
        <div class="np-card-head">
            <div class="np-card-title">
                <span class="np-ico np-ico-common">通</span>
                <div><h2>通用设置</h2><p>回调域名与部署相关</p></div>
            </div>
        </div>
        <div class="np-card-body">
            <div class="np-field">
                <label>回调基础地址</label>
                <div class="np-control">
                    <?php echo netpay_admin_input('pay_notify_base', $config['pay_notify_base'], 'text', 'placeholder="留空自动识别当前域名" maxlength="200"'); ?>
                    <div class="np-note">如站点部署在反向代理/CDN 后导致回调地址识别错误，可填写完整站点地址，例如 <code>https://www.example.com</code>。</div>
                </div>
            </div>
        </div>
    </section>

    <div class="np-savebar">
        <span>配置保存至 data/admin/pay.php，保存后立即生效。</span>
        <div>
            <button type="submit" class="np-btn np-btn-primary">保存配置</button>
        </div>
    </div>

    </form>

</div>
</div>
</div>
</div>
<script type="text/javascript">
(function(){
    var page = document.querySelector('.np-page');
    if (!page) { return; }

    function copyText(text, btn){
        function done(){
            var old = btn.innerHTML;
            btn.innerHTML = '已复制';
            setTimeout(function(){ btn.innerHTML = old; }, 1500);
        }
        function fallback(){
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(ta);
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, fallback);
        } else {
            fallback();
        }
    }

    page.onclick = function(e){
        var t = e.target || e.srcElement;
        if (t.className && (' ' + t.className + ' ').indexOf(' np-copy ') >= 0) {
            copyText(t.getAttribute('data-copy') || '', t);
            return;
        }
        if (t.className && (' ' + t.className + ' ').indexOf(' np-eye ') >= 0) {
            var input = document.getElementById(t.getAttribute('data-target'));
            if (input) {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                t.innerHTML = show ? '隐藏' : '显示';
            }
            return;
        }
        var tabs = page.querySelectorAll('.np-tabs a');
        for (var i = 0; i < tabs.length; i++) {
            if (tabs[i] === t) {
                for (var j = 0; j < tabs.length; j++) { tabs[j].className = ''; }
                t.className = 'active';
                break;
            }
        }
    };
})();
</script>
<?php viewFoot(); ?>
</body>
</html>
