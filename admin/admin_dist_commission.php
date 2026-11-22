<?php
require_once(dirname(__FILE__) . '/admin_dist_inc.php');
CheckPurview();

$operator = dist_admin_operator();
$pageUrl = 'admin_dist_commission.php';

function dist_commission_back_url()
{
    $params = array();
    foreach (array('st', 'level', 'kw', 'page') as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') {
            $params[$k] = preg_replace('/[^0-9A-Za-z_\x{4e00}-\x{9fa5}\-]/u', '', (string)$_GET[$k]);
        }
    }
    return 'admin_dist_commission.php' . (empty($params) ? '' : ('?' . http_build_query($params)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $do = isset($_POST['do']) ? (string)$_POST['do'] : '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $back = dist_commission_back_url();
    if ($do === 'release') {
        $res = dist_settle_one($id, $operator);
        ShowMsg($res['message'], $back);
        exit;
    }
    if ($do === 'void') {
        $reason = isset($_POST['reason']) ? trim((string)$_POST['reason']) : '后台作废';
        $res = dist_commission_void($id, $operator, $reason);
        ShowMsg($res['message'], $back);
        exit;
    }
    if ($do === 'settle_all') {
        $res = dist_cron_settle(500);
        dist_log(0, 'config_update', 'commission', 'batch', null, $res, 2, 0, '批量结算');
        ShowMsg('批量结算完成：成功 ' . intval($res['settled']) . ' 笔，失败 ' . intval($res['failed']) . ' 笔', $back);
        exit;
    }
    ShowMsg('未知操作', $back);
    exit;
}

$st = isset($_GET['st']) ? intval($_GET['st']) : -1;
$level = isset($_GET['level']) ? intval($_GET['level']) : 0;
$kw = isset($_GET['kw']) ? trim((string)$_GET['kw']) : '';
$page = max(1, dist_admin_page_param('page', 1));
$perPage = 25;

$where = '1=1';
if ($st >= 0 && $st <= 5) {
    $where .= " AND c.status={$st}";
}
if ($level >= 1 && $level <= 3) {
    $where .= " AND c.level={$level}";
}
if ($kw !== '') {
    $kwEsc = addslashes(substr($kw, 0, 40));
    $where .= " AND (c.order_no LIKE '%{$kwEsc}%' OR m.username LIKE '%{$kwEsc}%' OR c.uid=" . intval($kw) . ")";
}

if (isset($_GET['export'])) {
    $exportRows = array();
    $dsql->SetQuery("SELECT c.*,m.username AS uname,b.username AS bname FROM sea_dist_commission c
        LEFT JOIN sea_member m ON m.id=c.uid LEFT JOIN sea_member b ON b.id=c.buyer_uid
        WHERE {$where} ORDER BY c.id DESC");
    $dsql->Execute('dist_comms_export');
    while ($r = $dsql->GetArray('dist_comms_export')) {
        $exportRows[] = array(
            intval($r['create_time']) > 0 ? date('Y-m-d H:i:s', intval($r['create_time'])) : '',
            $r['order_no'],
            $r['bname'],
            $r['uname'],
            'L' . intval($r['level']),
            number_format(floatval($r['base_amount']), 2, '.', ''),
            number_format(floatval($r['rate']) * 100, 2, '.', '') . '%',
            number_format(floatval($r['amount']), 2, '.', ''),
            dist_commission_status_name($r['status']),
            intval($r['settle_time']) > 0 ? date('Y-m-d H:i:s', intval($r['settle_time'])) : '',
            $r['remark']
        );
    }
    dist_admin_export_csv('佣金流水_' . date('YmdHis'), array('时间', '订单号', '购买者', '收益人', '层级', '基数', '比例', '佣金', '状态', '预计结算', '备注'), $exportRows);
}

$total = 0;
$sum = 0.0;
$row = $dsql->GetOne("SELECT COUNT(*) AS dd,SUM(c.amount) AS s FROM sea_dist_commission c LEFT JOIN sea_member m ON m.id=c.uid WHERE {$where}");
if (is_array($row)) {
    $total = intval($row['dd']);
    $sum = floatval($row['s']);
}
$totalPage = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPage);
$offset = ($page - 1) * $perPage;
$rows = array();
$dsql->SetQuery("SELECT c.*,m.username AS uname,b.username AS bname FROM sea_dist_commission c
    LEFT JOIN sea_member m ON m.id=c.uid LEFT JOIN sea_member b ON b.id=c.buyer_uid
    WHERE {$where} ORDER BY c.id DESC LIMIT {$offset},{$perPage}");
$dsql->Execute('dist_comms');
while ($r = $dsql->GetArray('dist_comms')) {
    $rows[] = $r;
}

$baseQuery = array();
foreach (array('st' => $st >= 0 ? $st : '', 'level' => $level > 0 ? $level : '', 'kw' => $kw) as $k => $v) {
    if ($v !== '') {
        $baseQuery[$k] = $v;
    }
}
$baseQueryStr = empty($baseQuery) ? '' : (http_build_query($baseQuery) . '&');

dist_admin_head('佣金流水', 'admin_dist_commission.php');
?>
<div class="dist-panel">
  <h2>佣金流水 <span class="dist-help">共 <?php echo $total; ?> 条，合计 <?php echo number_format($sum, 2); ?> 元</span></h2>
  <form id="settle_form" method="post" style="display:none;" onsubmit="return confirm('将结算所有到期佣金，确认？');">
    <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
  </form>
  <form method="get" class="dist-filter">
    <button type="submit" name="do" value="settle_all" form="settle_form" class="dist-btn" style="height:30px;box-sizing:border-box;display:inline-flex;align-items:center;padding:0 12px;background:#33a47f;border-color:#33a47f;color:#fff;">批量结算到期佣金</button>
    <input type="text" name="kw" value="<?php echo dist_h($kw); ?>" placeholder="订单号 / 收益人 / UID">
    <select name="st">
      <option value="-1">全部状态</option>
      <?php for ($i = 0; $i <= 5; $i++): ?>
      <option value="<?php echo $i; ?>" <?php echo $st === $i ? 'selected' : ''; ?>><?php echo dist_h(dist_commission_status_name($i)); ?></option>
      <?php endfor; ?>
    </select>
    <select name="level">
      <option value="0">全部层级</option>
      <?php for ($i = 1; $i <= 3; $i++): ?>
      <option value="<?php echo $i; ?>" <?php echo $level === $i ? 'selected' : ''; ?>>L<?php echo $i; ?></option>
      <?php endfor; ?>
    </select>
    <button type="submit" class="dist-btn primary">筛选</button>
    <a href="admin_dist_commission.php" class="dist-btn">重置</a>
    <a href="admin_dist_commission.php?<?php echo dist_h($baseQueryStr); ?>export=1" class="dist-btn" onclick="return confirm('确认导出当前筛选结果（共 <?php echo $total; ?> 条）？');">导出Excel</a>
  </form>

  <table class="dist-table">
    <thead><tr><th>时间</th><th>订单号</th><th>购买者</th><th>收益人</th><th>层级</th><th>基数</th><th>比例</th><th>佣金</th><th>状态</th><th>预计结算</th><th>备注</th><th>操作</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="12" class="dist-empty">暂无数据</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?php echo date('Y-m-d H:i', intval($r['create_time'])); ?></td>
        <td style="max-width:150px;word-break:break-all;"><?php echo dist_h($r['order_no']); ?></td>
        <td><?php echo dist_h($r['bname']); ?></td>
        <td><?php echo dist_h($r['uname']); ?></td>
        <td>L<?php echo intval($r['level']); ?></td>
        <td><?php echo number_format(floatval($r['base_amount']), 2); ?></td>
        <td><?php echo number_format(floatval($r['rate']) * 100, 2); ?>%</td>
        <td><?php echo number_format(floatval($r['amount']), 2); ?></td>
        <td><span class="dist-badge dist-b<?php echo intval($r['status']); ?>"><?php echo dist_h(dist_commission_status_name($r['status'])); ?></span></td>
        <td><?php echo intval($r['settle_time']) > 0 ? date('Y-m-d', intval($r['settle_time'])) : '-'; ?></td>
        <td style="max-width:160px;"><?php echo dist_h($r['remark']); ?></td>
        <td>
          <?php if (intval($r['status']) === 0): ?>
          <form method="post" class="dist-inline">
            <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
            <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
            <button type="submit" name="do" value="release" class="dist-btn primary">放行</button>
          </form>
          <?php endif; ?>
          <?php if (in_array(intval($r['status']), array(0, 1, 2, 3), true)): ?>
          <form method="post" class="dist-inline" onsubmit="return confirm('确认作废该佣金？将同步扣减账户余额。');">
            <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
            <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
            <input type="hidden" name="reason" value="后台作废">
            <button type="submit" name="do" value="void" class="dist-btn danger">作废</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php echo dist_admin_pager($total, $page, $perPage, 'admin_dist_commission.php?' . $baseQueryStr); ?>
</div>
<?php dist_admin_foot(); ?>
