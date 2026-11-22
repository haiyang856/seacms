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
    $input['mazfu_alipay'] = isset($_POST['mazfu_alipay']) ? 'on' : 'off';
    $input['mazfu_wxpay'] = isset($_POST['mazfu_wxpay']) ? 'on' : 'off';
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

function netpay_admin_input($name, $value, $type = 'text', $extra = '')
{
    return '<input type="' . $type . '" name="' . netpay_html($name) . '" value="' . netpay_html($value) . '" ' . $extra . ' />';
}

function netpay_admin_switch($name, $value)
{
    $on = $value === 'on';
    return '<label style="margin-right:18px;"><input type="radio" name="' . netpay_html($name) . '" value="on"' . ($on ? ' checked' : '') . ' /> 开启</label>'
        . '<label><input type="radio" name="' . netpay_html($name) . '" value="off"' . (!$on ? ' checked' : '') . ' /> 关闭</label>';
}

$configFile = netpay_config_file();
$configWritable = (is_file($configFile) && is_writable($configFile)) || (!is_file($configFile) && is_writable(dirname($configFile)));
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>充值接口配置</title>
<link href="img/style.css" rel="stylesheet" type="text/css" />
<script src="js/common.js" type="text/javascript"></script>
<script src="js/main.js" type="text/javascript"></script>
<style type="text/css">
.netpay-note{color:#6b7280;font-size:12px;line-height:20px;padding-top:4px;}
.netpay-input{width:360px;max-width:90%;height:26px;line-height:26px;padding:0 6px;border:1px solid #d5dbe1;border-radius:3px;}
.netpay-input.small{width:120px;}
.netpay-textarea{width:92%;height:96px;padding:6px;border:1px solid #d5dbe1;border-radius:3px;font-family:Menlo,Consolas,monospace;font-size:12px;}
.netpay-error{background:#fff1f0;border:1px solid #ffccc7;color:#a8071a;padding:10px 14px;border-radius:4px;margin:10px 0;font-size:13px;line-height:22px;}
.netpay-ok{background:#f6ffed;border:1px solid #b7eb8f;color:#237804;padding:10px 14px;border-radius:4px;margin:10px 0;font-size:13px;}
.netpay-copy{color:#1677ff;cursor:pointer;margin-left:8px;font-size:12px;}
</style>
</head>
<body>
<script type="text/JavaScript">if(parent.$('admincpnav')) parent.$('admincpnav').innerHTML='后台首页&nbsp;&raquo;&nbsp;系统&nbsp;&raquo;&nbsp;充值接口配置';</script>
<div class="r_main">
<div class="r_content">
<div class="r_content_1">
<form action="admin_netpay.php?action=save" method="post" autocomplete="off">
<?php echo admin_csrf_field(); ?>

<?php if (!empty($errors)) { ?>
<div class="netpay-error">
    <strong>保存失败，请检查以下问题：</strong><br />
    <?php foreach ($errors as $err) { echo '· ' . netpay_html($err) . '<br />'; } ?>
</div>
<?php } ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tb_style">
<tbody>
<tr class="thead"><td colspan="2" class="td_title">充值接口配置</td></tr>
<tr>
<td width="180" align="right" class="td_border">配置文件</td>
<td align="left" class="td_border">
    <code>data/admin/pay.php</code>
    <span style="margin-left:12px;color:<?php echo $configWritable ? '#237804' : '#a8071a'; ?>;">
        <?php echo $configWritable ? '可读写' : '不可写，请检查文件权限'; ?>
    </span>
    <div class="netpay-note">所有在线充值参数统一保存在该文件中，保存后立即生效，无需重启服务。</div>
</td>
</tr>

<tr class="thead"><td colspan="2" class="td_title">站内积分定价</td></tr>
<tr>
<td align="right" class="td_border">人民币兑换比例</td>
<td align="left" class="td_border">
    1 元人民币兑换 <?php echo netpay_admin_input('pay_cny_points', $config['pay_cny_points'], 'text', 'class="netpay-input small"'); ?> 积分
    <div class="netpay-note">用户使用支付宝、微信、码支付时，按此比例计算到账积分。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border">USDT 兑换比例</td>
<td align="left" class="td_border">
    1 USDT 兑换 <?php echo netpay_admin_input('pay_usdt_points', $config['pay_usdt_points'], 'text', 'class="netpay-input small"'); ?> 积分
    <div class="netpay-note">用户使用 USDT 支付时，按此比例计算到账积分；修改后新订单实时生效。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border">单笔金额限制</td>
<td align="left" class="td_border">
    最低 <?php echo netpay_admin_input('pay_min_money', $config['pay_min_money'], 'text', 'class="netpay-input small"'); ?> 元/USDT
    &nbsp;&nbsp;最高 <?php echo netpay_admin_input('pay_max_money', $config['pay_max_money'], 'text', 'class="netpay-input small"'); ?> 元/USDT
</td>
</tr>
<tr>
<td align="right" class="td_border">订单有效期</td>
<td align="left" class="td_border">
    <?php echo netpay_admin_input('pay_order_expire', $config['pay_order_expire'], 'text', 'class="netpay-input small"'); ?> 秒
    <div class="netpay-note">建议 600 - 1800 秒，超时后第三方支付平台将不再接受付款。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border">商品名称</td>
<td align="left" class="td_border">
    <?php echo netpay_admin_input('pay_subject', $config['pay_subject'], 'text', 'class="netpay-input"'); ?>
</td>
</tr>

<tr class="thead"><td colspan="2" class="td_title">标准支付宝支付（手机网站 H5）</td></tr>
<tr>
<td align="right" class="td_border">启用状态</td>
<td align="left" class="td_border"><?php echo netpay_admin_switch('alipay_enable', $config['alipay_enable']); ?></td>
</tr>
<tr>
<td align="right" class="td_border">APPID</td>
<td align="left" class="td_border"><?php echo netpay_admin_input('alipay_appid', $config['alipay_appid'], 'text', 'class="netpay-input"'); ?></td>
</tr>
<tr>
<td align="right" class="td_border">应用私钥</td>
<td align="left" class="td_border">
    <textarea name="alipay_private_key" class="netpay-textarea" placeholder="支持 PKCS8/PKCS1，可粘贴纯 Base64 或完整 PEM"><?php echo netpay_html($config['alipay_private_key']); ?></textarea>
</td>
</tr>
<tr>
<td align="right" class="td_border">支付宝公钥</td>
<td align="left" class="td_border">
    <textarea name="alipay_public_key" class="netpay-textarea" placeholder="支付宝公钥或支付宝公钥证书"><?php echo netpay_html($config['alipay_public_key']); ?></textarea>
</td>
</tr>
<tr>
<td align="right" class="td_border">网关地址</td>
<td align="left" class="td_border">
    <?php echo netpay_admin_input('alipay_gateway', $config['alipay_gateway'], 'text', 'class="netpay-input"'); ?>
    <div class="netpay-note">正式环境：https://openapi.alipay.com/gateway.do；需签约「手机网站支付」并配置 H5 域名。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border">异步通知地址</td>
<td align="left" class="td_border">
    <input type="text" readonly class="netpay-input" value="<?php echo netpay_html(netpay_notify_url('alipay')); ?>" />
    <div class="netpay-note">需在支付宝开放平台配置为公网可访问的 HTTPS 地址。</div>
</td>
</tr>

<tr class="thead"><td colspan="2" class="td_title">标准微信支付（移动端 H5 / 桌面端扫码）</td></tr>
<tr>
<td align="right" class="td_border">启用状态</td>
<td align="left" class="td_border"><?php echo netpay_admin_switch('wechat_enable', $config['wechat_enable']); ?></td>
</tr>
<tr>
<td align="right" class="td_border">APPID</td>
<td align="left" class="td_border"><?php echo netpay_admin_input('wechat_appid', $config['wechat_appid'], 'text', 'class="netpay-input"'); ?></td>
</tr>
<tr>
<td align="right" class="td_border">商户号</td>
<td align="left" class="td_border"><?php echo netpay_admin_input('wechat_mchid', $config['wechat_mchid'], 'text', 'class="netpay-input"'); ?></td>
</tr>
<tr>
<td align="right" class="td_border">API 密钥</td>
<td align="left" class="td_border">
    <?php echo netpay_admin_input('wechat_key', $config['wechat_key'], 'text', 'class="netpay-input" autocomplete="new-password"'); ?>
    <div class="netpay-note">微信支付商户平台 APIv2 密钥（32 位），用于 MD5 签名。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border">支付场景</td>
<td align="left" class="td_border">
    <div class="netpay-note">移动端自动使用 H5 支付（需在商户平台配置 H5 支付域名），桌面端自动使用扫码支付。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border">下单地址</td>
<td align="left" class="td_border"><?php echo netpay_admin_input('wechat_gateway', $config['wechat_gateway'], 'text', 'class="netpay-input"'); ?></td>
</tr>
<tr>
<td align="right" class="td_border">异步通知地址</td>
<td align="left" class="td_border">
    <input type="text" readonly class="netpay-input" value="<?php echo netpay_html(netpay_notify_url('wechat')); ?>" />
</td>
</tr>

<tr class="thead"><td colspan="2" class="td_title">码支付（易支付协议）</td></tr>
<tr>
<td align="right" class="td_border">启用状态</td>
<td align="left" class="td_border"><?php echo netpay_admin_switch('mazfu_enable', $config['mazfu_enable']); ?></td>
</tr>
<tr>
<td align="right" class="td_border">商户 ID（pid）</td>
<td align="left" class="td_border"><?php echo netpay_admin_input('mazfu_pid', $config['mazfu_pid'], 'text', 'class="netpay-input"'); ?></td>
</tr>
<tr>
<td align="right" class="td_border">商户密钥</td>
<td align="left" class="td_border">
    <?php echo netpay_admin_input('mazfu_key', $config['mazfu_key'], 'text', 'class="netpay-input" autocomplete="new-password"'); ?>
</td>
</tr>
<tr>
<td align="right" class="td_border">提交地址</td>
<td align="left" class="td_border">
    <?php echo netpay_admin_input('mazfu_gateway', $config['mazfu_gateway'], 'text', 'class="netpay-input"'); ?>
    <div class="netpay-note">默认：https://www.mazfu.com/xpay/epay/submit.php（以码支付后台提供的地址为准）。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border">支付方式</td>
<td align="left" class="td_border">
    <label style="margin-right:18px;"><input type="checkbox" name="mazfu_alipay" value="on" <?php echo $config['mazfu_alipay'] === 'on' ? 'checked' : ''; ?> /> 支付宝</label>
    <label><input type="checkbox" name="mazfu_wxpay" value="on" <?php echo $config['mazfu_wxpay'] === 'on' ? 'checked' : ''; ?> /> 微信</label>
    <div class="netpay-note">未勾选的支付方式不会在前台展示。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border">异步通知地址</td>
<td align="left" class="td_border">
    <input type="text" readonly class="netpay-input" value="<?php echo netpay_html(netpay_notify_url('mazfu')); ?>" />
</td>
</tr>

<tr class="thead"><td colspan="2" class="td_title">USDT 收款（BEpusdt）</td></tr>
<tr>
<td align="right" class="td_border">启用状态</td>
<td align="left" class="td_border"><?php echo netpay_admin_switch('usdt_enable', $config['usdt_enable']); ?></td>
</tr>
<tr>
<td align="right" class="td_border">BEpusdt 地址</td>
<td align="left" class="td_border">
    <?php echo netpay_admin_input('usdt_gateway', $config['usdt_gateway'], 'text', 'class="netpay-input" placeholder="https://pay.example.com"'); ?>
    <div class="netpay-note">BEpusdt 服务根地址，不带末尾斜杠，例如 https://pay.example.com。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border">通信密钥</td>
<td align="left" class="td_border">
    <?php echo netpay_admin_input('usdt_token', $config['usdt_token'], 'text', 'class="netpay-input" autocomplete="new-password"'); ?>
    <div class="netpay-note">BEpusdt 后台配置的 API 通信密钥，用于下单与回调验签。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border">交易类型</td>
<td align="left" class="td_border">
    <?php echo netpay_admin_input('usdt_trade_type', $config['usdt_trade_type'], 'text', 'class="netpay-input small"'); ?>
    <div class="netpay-note">如 usdt.trc20、usdt.erc20、usdt.bep20，需与 BEpusdt 支持的交易类型一致。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border">异步通知地址</td>
<td align="left" class="td_border">
    <input type="text" readonly class="netpay-input" value="<?php echo netpay_html(netpay_notify_url('usdt')); ?>" />
</td>
</tr>

<tr class="thead"><td colspan="2" class="td_title">通用设置</td></tr>
<tr>
<td align="right" class="td_border">回调基础地址</td>
<td align="left" class="td_border">
    <?php echo netpay_admin_input('pay_notify_base', $config['pay_notify_base'], 'text', 'class="netpay-input" placeholder="留空自动识别当前域名"'); ?>
    <div class="netpay-note">如站点部署在反向代理/CDN 后导致回调地址识别错误，可在此填写完整站点地址，例如 https://www.example.com。</div>
</td>
</tr>
<tr>
<td align="right" class="td_border"></td>
<td align="left" class="td_border">
    <input type="submit" value="保存配置" class="btn" />
    <input type="button" value="返回用户充值记录" class="btn" onclick="location.href='admin_paylog.php';" style="margin-left:8px;" />
</td>
</tr>
</tbody>
</table>
</form>
</div>
</div>
</div>
<?php viewFoot(); ?>
</body>
</html>
