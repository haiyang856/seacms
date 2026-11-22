<?php
require_once(dirname(__FILE__) . '/admin_dist_inc.php');
CheckPurview();

$distActionMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $do = isset($_POST['do']) ? (string)$_POST['do'] : '';
    if ($do === 'settle') {
        $res = dist_cron_settle();
        $distActionMessage = '结算完成：成功 ' . intval($res['settled']) . ' 笔，失败 ' . intval($res['failed']) . ' 笔';
    } elseif ($do === 'activate') {
        $res = dist_cron_activate();
        $distActionMessage = '扫描 ' . intval($res['scanned']) . ' 人，自动开通 ' . intval($res['activated']) . ' 人';
    }
    dist_log(0, 'config_update', 'system', 'cron', null, array('action' => $do), 2, 0, $distActionMessage);
}

$summary = dist_admin_summary();
$series = dist_daily_series(14);
$maxAmount = 0.01;
foreach ($series as $s) {
    if ($s['amount'] > $maxAmount) {
        $maxAmount = $s['amount'];
    }
}

$chartPoints = array();
$chartCount = count($series);
foreach ($series as $i => $s) {
    $x = $chartCount > 1 ? ($i / ($chartCount - 1)) * 100 : 50;
    // 基线 96%，数值为 0 时也落在基线上绘制，不会缺失
    $y = 96 - ((float)$s['amount'] / $maxAmount) * 86;
    $chartPoints[] = array(
        'x' => round($x, 3),
        'y' => round($y, 3),
        'short' => substr($s['date'], 5),
        'tip' => $s['date'] . ' ￥' . number_format((float)$s['amount'], 2)
    );
}
$chartLine = array();
foreach ($chartPoints as $p) {
    $chartLine[] = $p['x'] . ',' . $p['y'];
}
$chartLine = implode(' ', $chartLine);
$chartArea = '0,96 ' . $chartLine . ' 100,96';

$recentWithdraw = array();
$dsql->SetQuery("SELECT w.*,m.username FROM sea_dist_withdraw w LEFT JOIN sea_member m ON m.id=w.uid WHERE w.status IN (0,1,2) ORDER BY w.id DESC LIMIT 8");
$dsql->Execute('dist_rw');
while ($r = $dsql->GetArray('dist_rw')) {
    $recentWithdraw[] = $r;
}
$recentCommission = array();
$dsql->SetQuery("SELECT c.*,m.username FROM sea_dist_commission c LEFT JOIN sea_member m ON m.id=c.uid ORDER BY c.id DESC LIMIT 10");
$dsql->Execute('dist_rc');
while ($r = $dsql->GetArray('dist_rc')) {
    $recentCommission[] = $r;
}

dist_admin_head('数据看板', 'admin_dist.php');
?>
<?php if ($distActionMessage !== ''): ?>
<div class="dist-tip"><?php echo dist_h($distActionMessage); ?></div>
<?php endif; ?>
<div class="dist-cards compact">
  <div class="dist-card blue"><div class="label">分销员总数</div><div class="value"><?php echo $summary['users']; ?></div><div class="sub">正常 <?php echo $summary['active_users']; ?> · 冻结 <?php echo $summary['frozen_users']; ?></div></div>
  <div class="dist-card green"><div class="label">可提现余额（元）</div><div class="value"><?php echo number_format($summary['available'], 2); ?></div><div class="sub">待结算 <?php echo number_format($summary['pending'], 2); ?> 元</div></div>
  <div class="dist-card orange"><div class="label">累计佣金（元）</div><div class="value"><?php echo number_format($summary['commission'], 2); ?></div><div class="sub">已提现 <?php echo number_format($summary['withdrawn'], 2); ?> 元</div></div>
  <div class="dist-card red"><div class="label">待处理</div><div class="value"><?php echo $summary['wait_withdraw']; ?> 提现</div><div class="sub">待结算佣金 <?php echo $summary['wait_commission']; ?> 笔 · 风险 <?php echo $summary['risk_commission']; ?> 笔</div></div>

  <div class="dist-card"><div class="label">计佣订单数</div><div class="value"><?php echo $summary['orders']; ?></div></div>
  <div class="dist-card"><div class="label">计佣基数合计（元）</div><div class="value"><?php echo number_format($summary['gmv'], 2); ?></div></div>
  <div class="dist-card"><div class="label">退款冲正（元）</div><div class="value"><?php echo number_format($summary['refund'], 2); ?></div></div>
  <div class="dist-card"><div class="label">负余额合计（元）</div><div class="value"><?php echo number_format($summary['negative'], 2); ?></div></div>
</div>

<style>
.dist-cards.compact{grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:10px 0;}
.dist-cards.compact .dist-card{padding:10px 12px;}
.dist-cards.compact .dist-card .label{font-size:11px;margin-bottom:4px;}
.dist-cards.compact .dist-card .value{font-size:18px;}
.dist-cards.compact .dist-card .sub{font-size:11px;margin-top:4px;}
@media (max-width:820px){.dist-cards.compact{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (max-width:520px){.dist-cards.compact{grid-template-columns:1fr;}}
.dist-chart{margin-top:6px;}
.dist-chart-plot{position:relative;height:140px;border-bottom:1px solid #eef0f3;}
.dist-chart-plot svg{position:absolute;inset:0;width:100%;height:100%;overflow:visible;}
.dist-chart-line{fill:none;stroke:#1667d1;stroke-width:2;stroke-linejoin:round;stroke-linecap:round;}
.dist-chart-axis{stroke:#e6ebf0;stroke-width:1;}
.dist-chart-dot{position:absolute;width:8px;height:8px;margin:-4px 0 0 -4px;border-radius:50%;background:#fff;border:2px solid #1667d1;box-sizing:border-box;}
.dist-chart-labels{position:relative;height:16px;margin-top:6px;}
.dist-chart-labels span{position:absolute;top:0;transform:translateX(-50%);font-size:10px;color:#98a2b3;white-space:nowrap;}
</style>

<div class="dist-panel">
  <form method="post" class="dist-inline">
    <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
    <input type="hidden" name="do" value="settle">
    <button type="submit" class="dist-btn primary">立即结算到期佣金</button>
  </form>
  <form method="post" class="dist-inline">
    <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
    <input type="hidden" name="do" value="activate">
    <button type="submit" class="dist-btn" style="background:#33a47f;border-color:#33a47f;color:#fff;">执行达标自动开通</button>
  </form>
  <span class="dist-help">建议每日执行一次，或配置定时任务访问后台该页面。</span>
</div>

<div class="dist-panel">
  <h2>近 14 日佣金趋势</h2>
  <div class="dist-chart">
    <div class="dist-chart-plot">
      <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
        <line x1="0" y1="96" x2="100" y2="96" class="dist-chart-axis"></line>
        <polygon points="<?php echo dist_h($chartArea); ?>" fill="#1667d1" fill-opacity="0.10"></polygon>
        <polyline points="<?php echo dist_h($chartLine); ?>" class="dist-chart-line" vector-effect="non-scaling-stroke"></polyline>
      </svg>
      <?php foreach ($chartPoints as $p): ?>
      <span class="dist-chart-dot" style="left:<?php echo $p['x']; ?>%;top:<?php echo $p['y']; ?>%;" title="<?php echo dist_h($p['tip']); ?>"></span>
      <?php endforeach; ?>
    </div>
    <div class="dist-chart-labels">
      <?php foreach ($chartPoints as $p): ?>
      <span style="left:<?php echo $p['x']; ?>%;"><?php echo dist_h($p['short']); ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="dist-panel">
  <h2>待处理提现</h2>
  <table class="dist-table">
    <thead><tr><th>单号</th><th>用户</th><th>金额</th><th>状态</th><th>申请时间</th><th>操作</th></tr></thead>
    <tbody>
    <?php if (empty($recentWithdraw)): ?>
      <tr><td colspan="6" class="dist-empty">暂无待处理提现</td></tr>
    <?php else: foreach ($recentWithdraw as $w): ?>
      <tr>
        <td><?php echo dist_h($w['withdraw_no']); ?></td>
        <td><?php echo dist_h($w['username']); ?></td>
        <td><?php echo number_format(floatval($w['amount']), 2); ?></td>
        <td><span class="dist-badge dist-b<?php echo intval($w['status']); ?>"><?php echo dist_h(dist_withdraw_status_name($w['status'])); ?></span></td>
        <td><?php echo date('Y-m-d H:i', intval($w['create_time'])); ?></td>
        <td><a class="dist-btn" href="admin_dist_withdraw.php?status=<?php echo intval($w['status']); ?>">去审核</a></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="dist-panel">
  <h2>最近佣金流水</h2>
  <table class="dist-table">
    <thead><tr><th>时间</th><th>收益人</th><th>层级</th><th>基数</th><th>比例</th><th>佣金</th><th>状态</th></tr></thead>
    <tbody>
    <?php if (empty($recentCommission)): ?>
      <tr><td colspan="7" class="dist-empty">暂无佣金记录</td></tr>
    <?php else: foreach ($recentCommission as $c): ?>
      <tr>
        <td><?php echo date('Y-m-d H:i', intval($c['create_time'])); ?></td>
        <td><?php echo dist_h($c['username']); ?></td>
        <td>L<?php echo intval($c['level']); ?></td>
        <td><?php echo number_format(floatval($c['base_amount']), 2); ?></td>
        <td><?php echo number_format(floatval($c['rate']) * 100, 2); ?>%</td>
        <td><?php echo number_format(floatval($c['amount']), 2); ?></td>
        <td><span class="dist-badge dist-b<?php echo intval($c['status']); ?>"><?php echo dist_h(dist_commission_status_name($c['status'])); ?></span></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php dist_admin_foot(); ?>
