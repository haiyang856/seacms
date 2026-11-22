<?php
require_once(dirname(__FILE__) . '/admin_dist_inc.php');
CheckPurview();

$operator = dist_admin_operator();
$pageUrl = 'admin_dist_withdraw.php';

function dist_withdraw_back_url()
{
    $params = array();
    foreach (array('st', 'kw', 'page') as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') {
            $params[$k] = preg_replace('/[^0-9A-Za-z_\x{4e00}-\x{9fa5}\-]/u', '', (string)$_GET[$k]);
        }
    }
    return 'admin_dist_withdraw.php' . (empty($params) ? '' : ('?' . http_build_query($params)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $do = isset($_POST['do']) ? (string)$_POST['do'] : '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $remark = isset($_POST['remark']) ? trim((string)$_POST['remark']) : '';
    $remark = RemoveXSS($remark);
    $back = dist_withdraw_back_url();

    if ($do === 'approve') {
        $res = dist_withdraw_audit($id, true, $operator, $remark);
        ShowMsg($res['message'], $back);
        exit;
    }
    if ($do === 'reject') {
        $res = dist_withdraw_audit($id, false, $operator, $remark);
        ShowMsg($res['message'], $back);
        exit;
    }
    if ($do === 'pay') {
        $tradeNo = isset($_POST['trade_no']) ? trim((string)$_POST['trade_no']) : '';
        $voucher = isset($_POST['voucher']) ? trim((string)$_POST['voucher']) : '';
        $tradeNo = RemoveXSS($tradeNo);
        $voucher = RemoveXSS($voucher);
        $res = dist_withdraw_pay($id, $operator, $tradeNo, $voucher);
        ShowMsg($res['message'], $back);
        exit;
    }
    if ($do === 'fail') {
        $res = dist_withdraw_fail($id, $operator, $remark);
        ShowMsg($res['message'], $back);
        exit;
    }
    if ($do === 'cancel') {
        $res = dist_withdraw_cancel($id, 0, $operator);
        ShowMsg($res['message'], $back);
        exit;
    }
    ShowMsg('未知操作', $back);
    exit;
}

$st = isset($_GET['st']) ? intval($_GET['st']) : -1;
$kw = isset($_GET['kw']) ? trim((string)$_GET['kw']) : '';
$page = max(1, dist_admin_page_param('page', 1));
$perPage = 20;

$where = '1=1';
if ($st >= 0 && $st <= 6) {
    $where .= " AND w.status={$st}";
}
if ($kw !== '') {
    $kwEsc = addslashes(substr($kw, 0, 40));
    $where .= " AND (w.withdraw_no LIKE '%{$kwEsc}%' OR m.username LIKE '%{$kwEsc}%' OR w.uid=" . intval($kw) . ")";
}

if (isset($_GET['export'])) {
    $exportRows = array();
    $dsql->SetQuery("SELECT w.*,m.username FROM sea_dist_withdraw w LEFT JOIN sea_member m ON m.id=w.uid WHERE {$where} ORDER BY w.id DESC");
    $dsql->Execute('dist_wds_export');
    while ($r = $dsql->GetArray('dist_wds_export')) {
        $exportRows[] = array(
            $r['withdraw_no'],
            $r['username'],
            intval($r['uid']),
            number_format(floatval($r['amount']), 2, '.', ''),
            number_format(floatval($r['fee']), 2, '.', ''),
            number_format(floatval($r['real_amount']), 2, '.', ''),
            $r['channel'],
            $r['account_info'],
            dist_withdraw_status_name($r['status']),
            intval($r['create_time']) > 0 ? date('Y-m-d H:i:s', intval($r['create_time'])) : '',
            $r['audit_admin'],
            intval($r['audit_time']) > 0 ? date('Y-m-d H:i:s', intval($r['audit_time'])) : '',
            $r['pay_admin'],
            intval($r['pay_time']) > 0 ? date('Y-m-d H:i:s', intval($r['pay_time'])) : '',
            $r['pay_trade_no'],
            $r['voucher'],
            $r['audit_remark']
        );
    }
    dist_admin_export_csv('提现审核_' . date('YmdHis'), array('单号', '用户', 'UID', '金额', '手续费', '实付', '方式', '收款账户', '状态', '申请时间', '审核人', '审核时间', '打款人', '打款时间', '打款流水号', '打款凭证', '备注'), $exportRows);
}

$total = 0;
$row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_withdraw w LEFT JOIN sea_member m ON m.id=w.uid WHERE {$where}");
if (is_array($row)) {
    $total = intval($row['dd']);
}
$totalPage = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPage);
$offset = ($page - 1) * $perPage;
$rows = array();
$dsql->SetQuery("SELECT w.*,m.username FROM sea_dist_withdraw w LEFT JOIN sea_member m ON m.id=w.uid WHERE {$where} ORDER BY w.id DESC LIMIT {$offset},{$perPage}");
$dsql->Execute('dist_wds');
while ($r = $dsql->GetArray('dist_wds')) {
    $rows[] = $r;
}

$baseQuery = array();
foreach (array('st' => $st >= 0 ? $st : '', 'kw' => $kw) as $k => $v) {
    if ($v !== '') {
        $baseQuery[$k] = $v;
    }
}
$baseQueryStr = empty($baseQuery) ? '' : (http_build_query($baseQuery) . '&');

dist_admin_head('提现审核', 'admin_dist_withdraw.php');
?>
<div class="dist-panel">
  <h2>提现申请 <span class="dist-help">共 <?php echo $total; ?> 条</span></h2>
  <form method="get" class="dist-filter">
    <input type="text" name="kw" value="<?php echo dist_h($kw); ?>" placeholder="单号 / 用户名 / UID">
    <select name="st">
      <option value="-1">全部状态</option>
      <?php for ($i = 0; $i <= 6; $i++): ?>
      <option value="<?php echo $i; ?>" <?php echo $st === $i ? 'selected' : ''; ?>><?php echo dist_h(dist_withdraw_status_name($i)); ?></option>
      <?php endfor; ?>
    </select>
    <button type="submit" class="dist-btn primary">筛选</button>
    <a href="admin_dist_withdraw.php" class="dist-btn">重置</a>
    <a href="admin_dist_withdraw.php?<?php echo dist_h($baseQueryStr); ?>export=1" class="dist-btn" onclick="return confirm('确认导出当前筛选结果（共 <?php echo $total; ?> 条）？');">导出Excel</a>
  </form>

  <table class="dist-table">
    <thead><tr><th>单号</th><th>用户</th><th>金额</th><th>手续费</th><th>实付</th><th>方式/账户</th><th>状态</th><th>申请时间</th><th>审核/打款</th><th>操作</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="10" class="dist-empty">暂无数据</td></tr>
    <?php else: foreach ($rows as $r): $status = intval($r['status']); ?>
      <tr>
        <td style="max-width:140px;word-break:break-all;"><?php echo dist_h($r['withdraw_no']); ?></td>
        <td><?php echo dist_h($r['username']); ?><br><span class="dist-help">UID <?php echo intval($r['uid']); ?></span></td>
        <td><?php echo number_format(floatval($r['amount']), 2); ?></td>
        <td><?php echo number_format(floatval($r['fee']), 2); ?></td>
        <td><?php echo number_format(floatval($r['real_amount']), 2); ?></td>
        <td style="max-width:180px;"><?php echo dist_h($r['channel']); ?><br><span class="dist-help"><?php echo dist_h($r['account_info']); ?></span></td>
        <td><span class="dist-badge dist-b<?php echo $status; ?>"><?php echo dist_h(dist_withdraw_status_name($status)); ?></span></td>
        <td><?php echo date('Y-m-d H:i', intval($r['create_time'])); ?></td>
        <td style="max-width:180px;">
          <span class="dist-help">审核：<?php echo dist_h($r['audit_admin']); ?> <?php echo intval($r['audit_time']) > 0 ? date('m-d H:i', intval($r['audit_time'])) : ''; ?></span><br>
          <span class="dist-help">打款：<?php echo dist_h($r['pay_admin']); ?> <?php echo intval($r['pay_time']) > 0 ? date('m-d H:i', intval($r['pay_time'])) : ''; ?></span>
          <?php if ($r['pay_trade_no'] !== ''): ?><br><span class="dist-help">流水：<?php echo dist_h($r['pay_trade_no']); ?></span><?php endif; ?>
          <?php if ($r['voucher'] !== ''): ?>
          <br><span class="dist-help">凭证：<?php if (preg_match('#^https?://#i', (string)$r['voucher'])): ?><a href="<?php echo dist_h($r['voucher']); ?>" target="_blank" rel="noopener noreferrer">查看凭证</a><?php else: ?><?php echo dist_h($r['voucher']); ?><?php endif; ?></span>
          <?php endif; ?>
          <?php if ($r['audit_remark'] !== ''): ?><br><span class="dist-help">备注：<?php echo dist_h($r['audit_remark']); ?></span><?php endif; ?>
        </td>
        <td style="min-width:240px;">
          <form method="post" class="dist-inline" style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
            <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
            <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
            <input type="text" name="remark" placeholder="备注" style="width:88px;height:26px;">
            <input type="text" name="trade_no" placeholder="流水号" title="打款交易号/回单号，仅在点击“确认打款”时保存" style="width:88px;height:26px;">
            <input type="text" name="voucher" placeholder="凭证" title="打款凭证图片地址或说明，仅在点击“确认打款”时保存" style="width:70px;height:26px;">
            <button type="submit" name="do" value="approve" class="dist-btn primary" onclick="return confirm('确认审核通过？若余额已被使用可能失败。');">通过</button>
            <button type="submit" name="do" value="reject" class="dist-btn danger" onclick="return confirm('驳回后金额退回可提现余额，确认？');">驳回</button>
            <button type="submit" name="do" value="pay" class="dist-btn primary" onclick="return confirm('确认已完成线下打款？');">确认打款</button>
            <button type="submit" name="do" value="fail" class="dist-btn warn" onclick="return confirm('标记打款失败并退回金额？');">打款失败</button>
            <button type="submit" name="do" value="cancel" class="dist-btn" onclick="return confirm('撤销后金额退回可提现余额，确认？');">撤销提现</button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php echo dist_admin_pager($total, $page, $perPage, 'admin_dist_withdraw.php?' . $baseQueryStr); ?>
</div>
<?php dist_admin_foot(); ?>
