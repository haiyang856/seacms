<?php
require_once(dirname(__FILE__) . '/admin_dist_inc.php');
CheckPurview();

$operator = dist_admin_operator();
$pageUrl = 'admin_dist_rule.php';

function dist_rule_float($value, $max = 1)
{
    $value = trim((string)$value);
    if ($value === '' || !preg_match('/^\d+(\.\d{1,4})?$/', $value)) {
        return false;
    }
    $f = round(floatval($value), 4);
    if ($f < 0 || $f > $max) {
        return false;
    }
    return $f;
}

function dist_rule_int($value, $min = 0, $max = 100000000)
{
    $value = trim((string)$value);
    if ($value === '' || !preg_match('/^\d+$/', $value)) {
        return false;
    }
    $i = intval($value);
    if ($i < $min || $i > $max) {
        return false;
    }
    return $i;
}

/**
 * 小数费率转百分比文案（0.1 => 10）
 */
function dist_rule_pct($value)
{
    $n = round(floatval($value) * 100, 2);
    $s = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $do = isset($_POST['do']) ? (string)$_POST['do'] : '';

    if ($do === 'save_settings') {
        $fields = array();
        foreach (array('enabled', 'level1', 'level2', 'level3', 'auto_activate', 'recharge_commission') as $k) {
            $v = isset($_POST[$k]) ? intval($_POST[$k]) : 0;
            $fields[$k] = $v === 1 ? 1 : 0;
        }
        foreach (array('rate1', 'rate2', 'rate3', 'max_rate1', 'max_rate2', 'max_rate3', 'max_total', 'grade_factor_1', 'grade_factor_2', 'grade_factor_3', 'grade_factor_4', 'withdraw_fee') as $k) {
            $v = isset($_POST[$k]) ? dist_rule_float($_POST[$k], 2) : false;
            if ($v === false) {
                ShowMsg('比例/系数配置格式不正确：' . $k, $pageUrl);
                exit;
            }
            $fields[$k] = $v;
        }
        foreach (array('points_per_yuan', 'exchange_rate') as $k) {
            $v = isset($_POST[$k]) ? dist_rule_int($_POST[$k], 1, 1000000) : false;
            if ($v === false) {
                ShowMsg('积分配置必须为正整数：' . $k, $pageUrl);
                exit;
            }
            $fields[$k] = $v;
        }
        foreach (array('observe_days', 'settle_batch', 'register_days') as $k) {
            $v = isset($_POST[$k]) ? dist_rule_int($_POST[$k], 0, 2000) : false;
            if ($v === false) {
                ShowMsg('数值配置不正确：' . $k, $pageUrl);
                exit;
            }
            $fields[$k] = $v;
        }
        foreach (array('min_withdraw', 'max_withdraw', 'consume_threshold', 'max_direct', 'max_team', 'max_commission_order', 'daily_commission_cap', 'monthly_commission_cap', 'withdraw_daily_limit') as $k) {
            $v = isset($_POST[$k]) ? dist_rule_int($_POST[$k], 0, 100000000) : false;
            if ($v === false) {
                ShowMsg('数值配置不正确：' . $k, $pageUrl);
                exit;
            }
            $fields[$k] = $v;
        }
        if ($fields['max_total'] > 0.2) {
            ShowMsg('三级合计比例不得超过 20%（0.2）', $pageUrl);
            exit;
        }
        foreach (array(1, 2, 3) as $lv) {
            if ($fields['rate' . $lv] > $fields['max_rate' . $lv]) {
                ShowMsg('L' . $lv . ' 基础比例不得超过单级上限', $pageUrl);
                exit;
            }
        }
        if ($fields['rate1'] + $fields['rate2'] + $fields['rate3'] > $fields['max_total'] + 0.00001) {
            ShowMsg('L1+L2+L3 合计不得超过总额上限', $pageUrl);
            exit;
        }
        if ($fields['min_withdraw'] > 0 && $fields['max_withdraw'] > 0 && $fields['min_withdraw'] > $fields['max_withdraw']) {
            ShowMsg('最低提现额不得高于单笔上限', $pageUrl);
            exit;
        }
        $content = "<?php\n\$dist_cfg_saved = " . var_export($fields, true) . ";\n";
        if (@file_put_contents(sea_ROOT . '/data/admin/dist.php', $content) === false) {
            ShowMsg('配置文件写入失败，请检查 data/admin 目录权限', $pageUrl);
            exit;
        }
        dist_log(0, 'config_update', 'config', 'dist', null, $fields, 2, 0, '保存分销基础配置');
        ShowMsg('配置已保存', $pageUrl);
        exit;
    }

    if ($do === 'save_global' || $do === 'add_scope') {
        $r1 = isset($_POST['rate1']) ? dist_rule_float($_POST['rate1']) : false;
        $r2 = isset($_POST['rate2']) ? dist_rule_float($_POST['rate2']) : false;
        $r3 = isset($_POST['rate3']) ? dist_rule_float($_POST['rate3']) : false;
        if ($r1 === false || $r2 === false || $r3 === false) {
            ShowMsg('费率格式不正确（0~1，最多 4 位小数）', $pageUrl);
            exit;
        }
        if ($r1 + $r2 + $r3 > 0.2001) {
            ShowMsg('三级合计不得超过 20%', $pageUrl);
            exit;
        }
        if ($r1 > floatval(dist_cfg('max_rate1', 0.15)) || $r2 > floatval(dist_cfg('max_rate2', 0.10)) || $r3 > floatval(dist_cfg('max_rate3', 0.08))) {
            ShowMsg('存在超过单级上限的费率', $pageUrl);
            exit;
        }
        $scopeType = $do === 'save_global' ? 1 : (isset($_POST['scope_type']) ? intval($_POST['scope_type']) : 0);
        $scopeId = $do === 'save_global' ? 0 : (isset($_POST['scope_id']) ? intval($_POST['scope_id']) : 0);
        if (!in_array($scopeType, array(1, 2, 3, 4, 5, 6), true)) {
            ShowMsg('规则范围类型不正确', $pageUrl);
            exit;
        }
        if ($scopeType !== 1 && $scopeId <= 0) {
            ShowMsg('请填写有效的范围 ID', $pageUrl);
            exit;
        }
        $effective = isset($_POST['effective_time']) ? strtotime(trim((string)$_POST['effective_time'])) : 0;
        if ($effective === false || $effective < 0) {
            $effective = 0;
        }
        $expire = isset($_POST['expire_time']) ? strtotime(trim((string)$_POST['expire_time'])) : 0;
        if ($expire === false || $expire < 0) {
            $expire = 0;
        }
        if ($effective > 0 && $expire > 0 && $expire <= $effective) {
            ShowMsg('失效时间必须晚于生效时间', $pageUrl);
            exit;
        }
        $now = time();
        if ($do === 'save_global') {
            $dsql->ExecuteNoneQuery("UPDATE sea_dist_rule SET status=2 WHERE scope_type=1 AND scope_id=0 AND status=1");
        }
        $row = $dsql->GetOne("SELECT MAX(version) AS v FROM sea_dist_rule WHERE scope_type={$scopeType} AND scope_id={$scopeId}");
        $version = (is_array($row) ? intval($row['v']) : 0) + 1;
        $effTime = $effective > 0 ? $effective : $now;
        $sql = "INSERT INTO sea_dist_rule (scope_type,scope_id,rate1,rate2,rate3,grade_factor,status,version,effective_time,expire_time,admin_id,create_time)
            VALUES ({$scopeType},{$scopeId}," . number_format($r1, 4, '.', '') . "," . number_format($r2, 4, '.', '') . "," . number_format($r3, 4, '.', '') . ",1.0000,1,{$version},{$effTime},{$expire}," . intval($_SESSION['sea_admin_id'] ?? 0) . ",{$now})";
        if (!$dsql->ExecuteNoneQuery($sql)) {
            ShowMsg('规则保存失败：' . $dsql->GetError(), $pageUrl);
            exit;
        }
        dist_log(0, 'config_update', 'rule', (string)$scopeType . ':' . $scopeId, null, array('rate1' => $r1, 'rate2' => $r2, 'rate3' => $r3, 'version' => $version), 2, 0, '保存分佣规则');
        ShowMsg('规则已生效（版本 v' . $version . '）', $pageUrl);
        exit;
    }

    if ($do === 'disable_rule') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $row = $dsql->GetOne("SELECT * FROM sea_dist_rule WHERE id={$id} limit 1");
        if (is_array($row) && intval($row['status']) === 1) {
            $dsql->ExecuteNoneQuery("UPDATE sea_dist_rule SET status=2 WHERE id={$id}");
            dist_log(0, 'config_update', 'rule', (string)$id, array('status' => 1), array('status' => 2), 2, 0, '停用规则');
            ShowMsg('规则已停用', $pageUrl);
            exit;
        }
        ShowMsg('规则不存在或已停用', $pageUrl);
        exit;
    }

    ShowMsg('未知操作', $pageUrl);
    exit;
}

$rules = array();
$dsql->SetQuery("SELECT * FROM sea_dist_rule WHERE status=1 ORDER BY scope_type ASC,scope_id ASC,version DESC");
$dsql->Execute('dist_rules');
while ($r = $dsql->GetArray('dist_rules')) {
    $rules[] = $r;
}
$cfgOut = $dist_cfg;
$scopeNames = array(1 => '全局', 2 => '分类', 3 => '单品视频', 4 => '会员套餐', 5 => '充值渠道', 6 => '等级系数');
$rateSum = floatval($cfgOut['rate1']) + floatval($cfgOut['rate2']) + floatval($cfgOut['rate3']);
dist_admin_head('分销规则配置', 'admin_dist_rule.php');
?>
<style>
.dist-rule-hero{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:0 0 10px;}
.dist-rule-hero .dist-card{padding:10px 12px;}
.dist-rule-hero .dist-card .label{font-size:11px;margin-bottom:4px;}
.dist-rule-hero .dist-card .value{font-size:18px;}
.dist-rule-hero .dist-card .sub{font-size:11px;margin-top:4px;line-height:1.4;}
.dist-rule-hero .dist-card .value .unit{font-size:11px;color:#98a2b3;font-weight:400;margin-left:2px;}
.dist-rule-hero .dist-card .value.small{font-size:15px;padding-top:2px;}
.dist-sum-line{background:#f0f7ff;border:1px solid #d6e8ff;color:#1667d1;padding:10px 14px;border-radius:6px;margin:0 0 14px;font-size:12px;line-height:1.7;}
.dist-rule-nav{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:10px 14px;background:#fff;border:1px solid #e6ebf0;border-radius:6px;margin:0 0 14px;}
.dist-rule-nav .nav-title{font-size:12px;font-weight:600;color:#606266;margin-right:2px;}
.dist-rule-nav a{padding:4px 12px;border-radius:14px;background:#f4f6fa;border:1px solid #e6ebf0;color:#4b5563;text-decoration:none;font-size:12px;line-height:20px;}
.dist-rule-nav a:hover{background:#e8f1ff;border-color:#b9d4fb;color:#1667d1;}
.dist-rule-section{background:#fff;border:1px solid #e6ebf0;border-radius:6px;margin:0 0 14px;overflow:hidden;scroll-margin-top:12px;}
.dist-rule-section-head{display:flex;align-items:center;flex-wrap:wrap;gap:6px 10px;padding:11px 16px;background:#fafbfc;border-bottom:1px solid #eef1f5;}
.dist-rule-section-head h3{margin:0;font-size:14px;font-weight:600;color:#1f2937;display:flex;align-items:center;gap:8px;}
.dist-rule-index{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;padding:0 6px;border-radius:10px;background:#e8f1ff;color:#1667d1;font-size:11px;font-weight:600;}
.dist-rule-section-head .hint{font-weight:400;color:#98a2b3;font-size:12px;}
.dist-rule-section-body{padding:16px;}
.dist-rule-section-body .dist-help{margin:0 0 12px;}
.dist-rule-section .dist-form-row{grid-template-columns:repeat(4,minmax(0,1fr));gap:14px 16px;}
.dist-rule-section .dist-form-row.rule-cols-3{grid-template-columns:repeat(3,minmax(0,1fr));}
.dist-rule-section .field-note{color:#98a2b3;font-size:11px;line-height:1.5;margin-top:5px;min-height:16px;}
.dist-rule-section input[readonly]{background:#f5f6f8;color:#98a2b3;}
.dist-rule-footer{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;background:#fff;border:1px solid #e6ebf0;border-radius:6px;padding:12px 16px;margin:0 0 14px;}
.dist-rule-footer .dist-help{margin:0;}
.dist-actions{display:flex;justify-content:flex-end;align-items:center;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid #f0f2f5;}
.dist-btn.lg{padding:8px 22px;font-size:13px;}
.dist-table-wrap{overflow-x:auto;}
.dist-scope-tag{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;line-height:18px;background:#f0f2f5;color:#4b5563;white-space:nowrap;}
.dist-scope-tag.global{background:#e8f1ff;color:#1667d1;}
.dist-rate{display:inline-block;min-width:54px;text-align:center;padding:2px 8px;border-radius:10px;background:#eef6ff;color:#1667d1;font-weight:600;font-size:11px;line-height:18px;}
.dist-rate.zero{background:#f5f6f8;color:#b0b7c3;font-weight:400;}
.dist-rate.sum{background:#e6f7eb;color:#0a7d32;}
.dist-validity{color:#606266;font-size:12px;white-space:nowrap;}
.dist-validity .sep{color:#c4c9d2;margin:0 4px;}
@media (max-width:1100px){.dist-rule-section .dist-form-row,.dist-rule-section .dist-form-row.rule-cols-3{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (max-width:760px){.dist-rule-hero{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (max-width:700px){.dist-rule-hero{grid-template-columns:1fr;}.dist-rule-section .dist-form-row,.dist-rule-section .dist-form-row.rule-cols-3{grid-template-columns:1fr;}.dist-rule-footer{justify-content:stretch;}.dist-rule-footer .dist-btn{flex:1;text-align:center;}.dist-actions .dist-btn{flex:1;text-align:center;}}
</style>

<div class="dist-rule-hero">
  <div class="dist-card blue">
    <div class="label">分销总开关</div>
    <div class="value small"><?php echo intval($cfgOut['enabled']) === 1 ? '已开启' : '已关闭'; ?></div>
    <div class="sub"><?php echo intval($cfgOut['auto_activate']) === 1 ? '达标自动开通分销身份' : '需人工审核开通'; ?></div>
  </div>
  <div class="dist-card green">
    <div class="label">L1 一级佣金</div>
    <div class="value"><?php echo dist_rule_pct($cfgOut['rate1']); ?><span class="unit">%</span></div>
    <div class="sub">单级上限 <?php echo dist_rule_pct($cfgOut['max_rate1']); ?>% · <?php echo intval($cfgOut['level1']) === 1 ? '已启用' : '已停用'; ?></div>
  </div>
  <div class="dist-card orange">
    <div class="label">L2 二级佣金</div>
    <div class="value"><?php echo dist_rule_pct($cfgOut['rate2']); ?><span class="unit">%</span></div>
    <div class="sub">单级上限 <?php echo dist_rule_pct($cfgOut['max_rate2']); ?>% · <?php echo intval($cfgOut['level2']) === 1 ? '已启用' : '已停用'; ?></div>
  </div>
  <div class="dist-card red">
    <div class="label">L3 三级佣金</div>
    <div class="value"><?php echo dist_rule_pct($cfgOut['rate3']); ?><span class="unit">%</span></div>
    <div class="sub">单级上限 <?php echo dist_rule_pct($cfgOut['max_rate3']); ?>% · <?php echo intval($cfgOut['level3']) === 1 ? '已启用' : '已停用'; ?></div>
  </div>
</div>

<div class="dist-sum-line">
  当前三级佣金合计 <strong><?php echo dist_rule_pct($rateSum); ?>%</strong>，合计硬上限 <strong><?php echo dist_rule_pct($cfgOut['max_total']); ?>%</strong>；单级比例不得超过对应单级上限。观察期 <strong><?php echo intval($cfgOut['observe_days']); ?></strong> 天，佣金到账后需观察期满方可结算。
</div>

<div class="dist-rule-nav">
  <span class="nav-title">快速定位</span>
  <a href="#grp-switch">运营开关</a>
  <a href="#grp-rate">佣金比例</a>
  <a href="#grp-points">积分与结算</a>
  <a href="#grp-withdraw">提现设置</a>
  <a href="#grp-risk">开通与风控</a>
  <a href="#grp-grade">等级系数</a>
</div>

<form method="post">
  <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
  <input type="hidden" name="do" value="save_settings">

  <div class="dist-rule-section" id="grp-switch">
    <div class="dist-rule-section-head">
      <h3><span class="dist-rule-index">01</span>运营开关</h3>
      <span class="hint">控制分销体系、各级佣金启用与开通方式</span>
    </div>
    <div class="dist-rule-section-body">
      <div class="dist-form-row">
        <div><label>分销总开关</label><select name="enabled"><option value="1" <?php echo intval($cfgOut['enabled']) === 1 ? 'selected' : ''; ?>>开启</option><option value="0" <?php echo intval($cfgOut['enabled']) !== 1 ? 'selected' : ''; ?>>关闭</option></select></div>
        <div><label>L1 一级佣金</label><select name="level1"><option value="1" <?php echo intval($cfgOut['level1']) === 1 ? 'selected' : ''; ?>>启用</option><option value="0" <?php echo intval($cfgOut['level1']) !== 1 ? 'selected' : ''; ?>>停用</option></select></div>
        <div><label>L2 二级佣金</label><select name="level2"><option value="1" <?php echo intval($cfgOut['level2']) === 1 ? 'selected' : ''; ?>>启用</option><option value="0" <?php echo intval($cfgOut['level2']) !== 1 ? 'selected' : ''; ?>>停用</option></select></div>
        <div><label>L3 三级佣金</label><select name="level3"><option value="1" <?php echo intval($cfgOut['level3']) === 1 ? 'selected' : ''; ?>>启用</option><option value="0" <?php echo intval($cfgOut['level3']) !== 1 ? 'selected' : ''; ?>>停用</option></select></div>
        <div><label>自动开通分销身份</label><select name="auto_activate"><option value="1" <?php echo intval($cfgOut['auto_activate']) === 1 ? 'selected' : ''; ?>>达标自动开通</option><option value="0" <?php echo intval($cfgOut['auto_activate']) !== 1 ? 'selected' : ''; ?>>人工审核</option></select></div>
        <div><label>充值计佣</label><select name="recharge_commission"><option value="0" <?php echo intval($cfgOut['recharge_commission']) !== 1 ? 'selected' : ''; ?>>关闭（推荐）</option><option value="1" <?php echo intval($cfgOut['recharge_commission']) === 1 ? 'selected' : ''; ?>>开启</option></select></div>
      </div>
    </div>
  </div>

  <div class="dist-rule-section" id="grp-rate">
    <div class="dist-rule-section-head">
      <h3><span class="dist-rule-index">02</span>佣金比例与上限</h3>
      <span class="hint">小数形式，如 0.10 表示 10%</span>
    </div>
    <div class="dist-rule-section-body">
      <div class="dist-form-row">
        <div><label>L1 基础比例</label><input type="text" name="rate1" value="<?php echo dist_h($cfgOut['rate1']); ?>"><div class="field-note">一级下线真实消费的分佣比例</div></div>
        <div><label>L2 基础比例</label><input type="text" name="rate2" value="<?php echo dist_h($cfgOut['rate2']); ?>"><div class="field-note">二级下线真实消费的分佣比例</div></div>
        <div><label>L3 基础比例</label><input type="text" name="rate3" value="<?php echo dist_h($cfgOut['rate3']); ?>"><div class="field-note">三级下线真实消费的分佣比例</div></div>
        <div><label>L1 单级上限</label><input type="text" name="max_rate1" value="<?php echo dist_h($cfgOut['max_rate1']); ?>"><div class="field-note">规则调整时一级不得超过该值</div></div>
        <div><label>L2 单级上限</label><input type="text" name="max_rate2" value="<?php echo dist_h($cfgOut['max_rate2']); ?>"><div class="field-note">规则调整时二级不得超过该值</div></div>
        <div><label>L3 单级上限</label><input type="text" name="max_rate3" value="<?php echo dist_h($cfgOut['max_rate3']); ?>"><div class="field-note">规则调整时三级不得超过该值</div></div>
        <div><label>三级合计上限</label><input type="text" name="max_total" value="<?php echo dist_h($cfgOut['max_total']); ?>"><div class="field-note">硬上限 0.20（20%），超出将被拒绝</div></div>
      </div>
    </div>
  </div>

  <div class="dist-rule-section" id="grp-points">
    <div class="dist-rule-section-head">
      <h3><span class="dist-rule-index">03</span>积分与结算</h3>
      <span class="hint">积分换算、观察期与批量结算</span>
    </div>
    <div class="dist-rule-section-body">
      <div class="dist-form-row">
        <div><label>积分公允值（积分/元）</label><input type="text" name="points_per_yuan" value="<?php echo intval($cfgOut['points_per_yuan']); ?>"><div class="field-note">100 表示 100 积分 = 1 元</div></div>
        <div><label>佣金兑换积分（积分/元）</label><input type="text" name="exchange_rate" value="<?php echo intval($cfgOut['exchange_rate']); ?>"><div class="field-note">1 元佣金可兑换的积分数</div></div>
        <div><label>观察期（天）</label><input type="text" name="observe_days" value="<?php echo intval($cfgOut['observe_days']); ?>"><div class="field-note">T+N 后可结算，默认 7 天</div></div>
        <div><label>结算批大小</label><input type="text" name="settle_batch" value="<?php echo intval($cfgOut['settle_batch']); ?>"><div class="field-note">定时结算每次处理的流水条数</div></div>
      </div>
    </div>
  </div>

  <div class="dist-rule-section" id="grp-withdraw">
    <div class="dist-rule-section-head">
      <h3><span class="dist-rule-index">04</span>提现设置</h3>
      <span class="hint">金额单位：元；手续费率用小数表示</span>
    </div>
    <div class="dist-rule-section-body">
      <div class="dist-form-row">
        <div><label>最低提现（元）</label><input type="text" name="min_withdraw" value="<?php echo intval($cfgOut['min_withdraw']); ?>"></div>
        <div><label>单笔提现上限（元）</label><input type="text" name="max_withdraw" value="<?php echo intval($cfgOut['max_withdraw']); ?>"><div class="field-note">0 表示不限制单笔金额</div></div>
        <div><label>提现手续费率</label><input type="text" name="withdraw_fee" value="<?php echo dist_h($cfgOut['withdraw_fee']); ?>"><div class="field-note">0.02 表示 2%</div></div>
        <div><label>每日提现次数</label><input type="text" name="withdraw_daily_limit" value="<?php echo intval($cfgOut['withdraw_daily_limit']); ?>"><div class="field-note">0 表示不限制次数</div></div>
      </div>
    </div>
  </div>

  <div class="dist-rule-section" id="grp-risk">
    <div class="dist-rule-section-head">
      <h3><span class="dist-rule-index">05</span>开通门槛与风控</h3>
      <span class="hint">人数 / 佣金类上限为 0 时不限制</span>
    </div>
    <div class="dist-rule-section-body">
      <div class="dist-form-row">
        <div><label>达标消费门槛（元）</label><input type="text" name="consume_threshold" value="<?php echo intval($cfgOut['consume_threshold']); ?>"><div class="field-note">累计有效消费达标后自动开通分销员</div></div>
        <div><label>注册冷静期（天）</label><input type="text" name="register_days" value="<?php echo intval($cfgOut['register_days']); ?>"><div class="field-note">注册后多少天内可补绑邀请人</div></div>
        <div><label>直推人数上限</label><input type="text" name="max_direct" value="<?php echo intval($cfgOut['max_direct']); ?>"></div>
        <div><label>团队总人数上限</label><input type="text" name="max_team" value="<?php echo intval($cfgOut['max_team']); ?>"></div>
        <div><label>单笔佣金上限（元）</label><input type="text" name="max_commission_order" value="<?php echo intval($cfgOut['max_commission_order']); ?>"></div>
        <div><label>单日佣金上限（元）</label><input type="text" name="daily_commission_cap" value="<?php echo intval($cfgOut['daily_commission_cap']); ?>"></div>
        <div><label>单月佣金上限（元）</label><input type="text" name="monthly_commission_cap" value="<?php echo intval($cfgOut['monthly_commission_cap']); ?>"></div>
      </div>
    </div>
  </div>

  <div class="dist-rule-section" id="grp-grade">
    <div class="dist-rule-section-head">
      <h3><span class="dist-rule-index">06</span>等级系数</h3>
      <span class="hint">普通会员固定 1.0，按荣誉等级放大或收窄佣金</span>
    </div>
    <div class="dist-rule-section-body">
      <div class="dist-form-row">
        <div><label>普通会员系数</label><input type="text" value="<?php echo dist_h($cfgOut['grade_factor_1']); ?>" readonly><div class="field-note">固定 1.0，不可修改</div></div>
        <div><label>铜牌系数</label><input type="text" name="grade_factor_2" value="<?php echo dist_h($cfgOut['grade_factor_2']); ?>"></div>
        <div><label>银牌系数</label><input type="text" name="grade_factor_3" value="<?php echo dist_h($cfgOut['grade_factor_3']); ?>"></div>
        <div><label>金牌系数</label><input type="text" name="grade_factor_4" value="<?php echo dist_h($cfgOut['grade_factor_4']); ?>"></div>
      </div>
    </div>
  </div>

  <input type="hidden" name="grade_factor_1" value="<?php echo dist_h($cfgOut['grade_factor_1']); ?>">
  <div class="dist-rule-footer">
    <span class="dist-help">修改后立即生效；已产生的订单按支付时快照执行，不追溯。</span>
    <button type="submit" class="dist-btn primary lg">保存配置</button>
  </div>
</form>

<div class="dist-rule-section">
  <div class="dist-rule-section-head">
    <h3>全局分佣规则</h3>
    <span class="hint">发布新版本并停用旧版本</span>
  </div>
  <div class="dist-rule-section-body">
    <p class="dist-help">当前基础比例：L1 <?php echo dist_rule_pct($cfgOut['rate1']); ?>% / L2 <?php echo dist_rule_pct($cfgOut['rate2']); ?>% / L3 <?php echo dist_rule_pct($cfgOut['rate3']); ?>%。历史订单不受影响。</p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
      <input type="hidden" name="do" value="save_global">
      <div class="dist-form-row rule-cols-3">
        <div><label>L1 比例</label><input type="text" name="rate1" value="<?php echo dist_h($cfgOut['rate1']); ?>"></div>
        <div><label>L2 比例</label><input type="text" name="rate2" value="<?php echo dist_h($cfgOut['rate2']); ?>"></div>
        <div><label>L3 比例</label><input type="text" name="rate3" value="<?php echo dist_h($cfgOut['rate3']); ?>"></div>
      </div>
      <div class="dist-actions"><button type="submit" class="dist-btn primary lg">发布全局规则</button></div>
    </form>
  </div>
</div>

<div class="dist-rule-section">
  <div class="dist-rule-section-head">
    <h3>差异化规则</h3>
    <span class="hint">按分类 / 单品 / 套餐 / 渠道 / 等级单独设佣</span>
  </div>
  <div class="dist-rule-section-body">
    <p class="dist-help">范围类型：2 分类 / 3 单品视频 / 4 会员套餐 / 5 充值渠道 / 6 等级系数；范围 ID 填对应对象 ID。</p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
      <input type="hidden" name="do" value="add_scope">
      <div class="dist-form-row">
        <div><label>范围类型</label><select name="scope_type"><option value="3">3 单品视频</option><option value="2">2 分类</option><option value="4">4 会员套餐</option><option value="5">5 充值渠道</option><option value="6">6 等级系数</option></select></div>
        <div><label>范围 ID</label><input type="text" name="scope_id" placeholder="视频ID / 分类ID / 会员组ID / 等级"></div>
        <div><label>生效时间</label><input type="text" name="effective_time" placeholder="留空立即生效"></div>
        <div><label>失效时间</label><input type="text" name="expire_time" placeholder="留空长期有效"></div>
        <div><label>L1 比例</label><input type="text" name="rate1" value="0.10"></div>
        <div><label>L2 比例</label><input type="text" name="rate2" value="0.05"></div>
        <div><label>L3 比例</label><input type="text" name="rate3" value="0.03"></div>
      </div>
      <div class="dist-actions"><button type="submit" class="dist-btn primary lg">添加规则</button></div>
    </form>
  </div>
</div>

<div class="dist-rule-section">
  <div class="dist-rule-section-head">
    <h3>当前生效规则</h3>
    <span class="hint">仅显示生效中的最新版本，停用后自动回退到基础配置</span>
  </div>
  <div class="dist-rule-section-body">
    <div class="dist-table-wrap">
    <table class="dist-table">
      <thead><tr><th>ID</th><th>作用范围</th><th>范围ID</th><th>L1</th><th>L2</th><th>L3</th><th>合计</th><th>版本</th><th>有效期</th><th>操作</th></tr></thead>
      <tbody>
      <?php if (empty($rules)): ?>
        <tr><td colspan="10" class="dist-empty">暂无生效规则，将使用基础配置费率</td></tr>
      <?php else: foreach ($rules as $r):
          $scopeType = intval($r['scope_type']);
          $scopeId = intval($r['scope_id']);
          $r1 = floatval($r['rate1']);
          $r2 = floatval($r['rate2']);
          $r3 = floatval($r['rate3']);
          $rowSum = $r1 + $r2 + $r3;
          $startText = intval($r['effective_time']) > 0 ? date('Y-m-d H:i', intval($r['effective_time'])) : '立即生效';
          $endText = intval($r['expire_time']) > 0 ? date('Y-m-d H:i', intval($r['expire_time'])) : '长期有效';
      ?>
        <tr>
          <td><?php echo intval($r['id']); ?></td>
          <td><span class="dist-scope-tag<?php echo $scopeType === 1 ? ' global' : ''; ?>"><?php echo dist_h(isset($scopeNames[$scopeType]) ? $scopeNames[$scopeType] : ('类型' . $scopeType)); ?></span></td>
          <td><?php echo $scopeType === 1 ? '—' : $scopeId; ?></td>
          <td><span class="dist-rate<?php echo $r1 <= 0 ? ' zero' : ''; ?>"><?php echo number_format($r1 * 100, 2); ?>%</span></td>
          <td><span class="dist-rate<?php echo $r2 <= 0 ? ' zero' : ''; ?>"><?php echo number_format($r2 * 100, 2); ?>%</span></td>
          <td><span class="dist-rate<?php echo $r3 <= 0 ? ' zero' : ''; ?>"><?php echo number_format($r3 * 100, 2); ?>%</span></td>
          <td><span class="dist-rate sum"><?php echo number_format($rowSum * 100, 2); ?>%</span></td>
          <td>v<?php echo intval($r['version']); ?></td>
          <td><span class="dist-validity"><?php echo dist_h($startText); ?><span class="sep">~</span><?php echo dist_h($endText); ?></span></td>
          <td>
            <form method="post" class="dist-inline" onsubmit="return confirm('确认停用该规则？');">
              <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
              <input type="hidden" name="do" value="disable_rule">
              <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
              <button type="submit" class="dist-btn danger">停用</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php dist_admin_foot(); ?>
