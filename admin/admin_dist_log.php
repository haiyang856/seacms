<?php
require_once(dirname(__FILE__) . '/admin_dist_inc.php');
CheckPurview();

$pageUrl = 'admin_dist_log.php';
$action = isset($_GET['action_name']) ? trim((string)$_GET['action_name']) : '';
$uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
$page = max(1, dist_admin_page_param('page', 1));
$perPage = 30;
$actions = array(
    'bind' => '关系绑定',
    'audit' => '身份审核',
    'commission_adjust' => '佣金调整',
    'withdraw_audit' => '提现处理',
    'config_update' => '配置变更'
);

$where = '1=1';
if ($action !== '') {
    $where .= " AND action='" . addslashes(substr($action, 0, 32)) . "'";
}
if ($uid > 0) {
    $where .= " AND uid={$uid}";
}

if (isset($_GET['export'])) {
    $exportRows = array();
    $dsql->SetQuery("SELECT l.*,m.username FROM sea_dist_log l LEFT JOIN sea_member m ON m.id=l.uid WHERE {$where} ORDER BY l.id DESC");
    $dsql->Execute('dist_logs_export');
    while ($r = $dsql->GetArray('dist_logs_export')) {
        $exportRows[] = array(
            intval($r['create_time']) > 0 ? date('Y-m-d H:i:s', intval($r['create_time'])) : '',
            $r['username'],
            intval($r['uid']),
            isset($actions[$r['action']]) ? $actions[$r['action']] : $r['action'],
            $r['target_type'],
            $r['target_id'],
            $r['before_data'],
            $r['after_data'],
            intval($r['operator_type']) === 2 ? '后台' : (intval($r['operator_type']) === 1 ? '会员' : '系统'),
            $r['ip'],
            $r['remark']
        );
    }
    dist_admin_export_csv('审计日志_' . date('YmdHis'), array('时间', '用户', 'UID', '动作', '对象类型', '对象编号', '变更前', '变更后', '操作人', 'IP', '备注'), $exportRows);
}

$total = 0;
$row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_log WHERE {$where}");
if (is_array($row)) {
    $total = intval($row['dd']);
}
$totalPage = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPage);
$offset = ($page - 1) * $perPage;
$rows = array();
$dsql->SetQuery("SELECT l.*,m.username FROM sea_dist_log l LEFT JOIN sea_member m ON m.id=l.uid WHERE {$where} ORDER BY l.id DESC LIMIT {$offset},{$perPage}");
$dsql->Execute('dist_logs');
while ($r = $dsql->GetArray('dist_logs')) {
    $rows[] = $r;
}
$baseQuery = array();
if ($action !== '') {
    $baseQuery['action_name'] = $action;
}
if ($uid > 0) {
    $baseQuery['uid'] = $uid;
}
$baseQueryStr = empty($baseQuery) ? '' : (http_build_query($baseQuery) . '&');

dist_admin_head('分销审计日志', 'admin_dist_log.php');
?>
<div class="dist-panel">
  <h2>审计日志 <span class="dist-help">只追加、不删除，共 <?php echo $total; ?> 条</span></h2>
  <form method="get" class="dist-filter">
    <select name="action_name">
      <option value="">全部动作</option>
      <?php foreach ($actions as $k => $v): ?>
      <option value="<?php echo dist_h($k); ?>" <?php echo $action === $k ? 'selected' : ''; ?>><?php echo dist_h($v); ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="uid" value="<?php echo $uid > 0 ? $uid : ''; ?>" placeholder="用户 UID">
    <button type="submit" class="dist-btn primary">筛选</button>
    <a href="admin_dist_log.php" class="dist-btn">重置</a>
    <a href="admin_dist_log.php?<?php echo dist_h($baseQueryStr); ?>export=1" class="dist-btn" onclick="return confirm('确认导出当前筛选结果（共 <?php echo $total; ?> 条）？');">导出Excel</a>
  </form>

  <table class="dist-table">
    <thead><tr><th>时间</th><th>用户</th><th>动作</th><th>对象</th><th>变更前</th><th>变更后</th><th>操作人</th><th>IP</th><th>备注</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="9" class="dist-empty">暂无日志</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?php echo date('Y-m-d H:i:s', intval($r['create_time'])); ?></td>
        <td><?php echo dist_h($r['username']); ?><br><span class="dist-help">UID <?php echo intval($r['uid']); ?></span></td>
        <td><?php echo dist_h(isset($actions[$r['action']]) ? $actions[$r['action']] : $r['action']); ?></td>
        <td><?php echo dist_h($r['target_type']); ?><br><span class="dist-help"><?php echo dist_h($r['target_id']); ?></span></td>
        <td style="max-width:220px;word-break:break-all;font-size:11px;color:#888;"><?php echo dist_h($r['before_data']); ?></td>
        <td style="max-width:260px;word-break:break-all;font-size:11px;color:#888;"><?php echo dist_h($r['after_data']); ?></td>
        <td><?php echo intval($r['operator_type']) === 2 ? '后台' : (intval($r['operator_type']) === 1 ? '会员' : '系统'); ?></td>
        <td><?php echo dist_h($r['ip']); ?></td>
        <td style="max-width:200px;"><?php echo dist_h($r['remark']); ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php echo dist_admin_pager($total, $page, $perPage, 'admin_dist_log.php?' . $baseQueryStr); ?>
</div>
<?php dist_admin_foot(); ?>
