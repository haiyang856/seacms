<?php
require_once(dirname(__FILE__) . '/admin_dist_inc.php');
CheckPurview();

$operator = dist_admin_operator();
$pageUrl = 'admin_dist_user.php';

function dist_user_back_url($extra = '')
{
    $params = array();
    foreach (array('kw', 'status', 'grade', 'page') as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') {
            $params[$k] = preg_replace('/[^0-9A-Za-z_\x{4e00}-\x{9fa5}\-]/u', '', (string)$_GET[$k]);
        }
    }
    return 'admin_dist_user.php' . (empty($params) ? '' : ('?' . http_build_query($params)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $do = isset($_POST['do']) ? (string)$_POST['do'] : '';
    $target = isset($_POST['uid']) ? intval($_POST['uid']) : 0;
    $row = $target > 0 ? dist_user_get($target) : array();
    $back = dist_user_back_url();
    if (empty($row)) {
        ShowMsg('分销员不存在', $back);
        exit;
    }
    $oldStatus = intval($row['status']);
    $now = time();

    if ($do === 'freeze') {
        if ($oldStatus !== 1) {
            ShowMsg('仅正常状态可冻结', $back);
            exit;
        }
        $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=2,update_time={$now} WHERE uid={$target}");
        dist_log($target, 'audit', 'user', (string)$target, array('status' => $oldStatus), array('status' => 2), 2, 0, '冻结分销身份');
        ShowMsg('已冻结该分销员', $back);
        exit;
    }

    if ($do === 'unfreeze') {
        if (!in_array($oldStatus, array(2, 4, 3), true)) {
            ShowMsg('当前状态无需解冻', $back);
            exit;
        }
        $code = (string)$row['invite_code'];
        if ($code === '') {
            $code = dist_generate_invite_code();
        }
        $codeEsc = addslashes($code);
        $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=1,invite_code='{$codeEsc}',update_time={$now} WHERE uid={$target}");
        dist_log($target, 'audit', 'user', (string)$target, array('status' => $oldStatus), array('status' => 1), 2, 0, '恢复分销身份');
        ShowMsg('已恢复为正常状态', $back);
        exit;
    }

    if ($do === 'kick') {
        if ($oldStatus !== 1) {
            ShowMsg('仅正常状态可清退', $back);
            exit;
        }
        $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET status=4,update_time={$now} WHERE uid={$target}");
        dist_log($target, 'audit', 'user', (string)$target, array('status' => $oldStatus), array('status' => 4), 2, 0, '清退分销员');
        ShowMsg('已清退，历史关系保留但停止计佣', $back);
        exit;
    }

    if ($do === 'reset_code') {
        $code = dist_generate_invite_code();
        $codeEsc = addslashes($code);
        $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET invite_code='{$codeEsc}',update_time={$now} WHERE uid={$target}");
        dist_log($target, 'audit', 'user', (string)$target, array('invite_code' => $row['invite_code']), array('invite_code' => $code), 2, 0, '重置邀请码');
        ShowMsg('邀请码已重置为 ' . $code . '，旧码立即失效', $back);
        exit;
    }

    if ($do === 'set_grade') {
        $grade = isset($_POST['grade']) ? intval($_POST['grade']) : 0;
        if (!in_array($grade, array(1, 2, 3, 4), true)) {
            ShowMsg('等级不正确', $back);
            exit;
        }
        $dsql->ExecuteNoneQuery("UPDATE sea_dist_user SET grade={$grade},update_time={$now} WHERE uid={$target}");
        dist_log($target, 'audit', 'user', (string)$target, array('grade' => intval($row['grade'])), array('grade' => $grade), 2, 0, '调整分销等级');
        ShowMsg('等级已调整为' . dist_grade_name($grade), $back);
        exit;
    }

    if ($do === 'open') {
        if ($oldStatus === 1) {
            ShowMsg('该用户已是正常分销员', $back);
            exit;
        }
        $res = dist_try_activate($target, true);
        if (empty($res['ok'])) {
            dist_user_create($target, 1);
        }
        dist_log($target, 'audit', 'user', (string)$target, array('status' => $oldStatus), array('status' => 1), 2, 0, '后台手动开通');
        ShowMsg('已手动开通分销身份', $back);
        exit;
    }

    ShowMsg('未知操作', $back);
    exit;
}

$kw = isset($_GET['kw']) ? trim((string)$_GET['kw']) : '';
$status = isset($_GET['status']) ? intval($_GET['status']) : -1;
$grade = isset($_GET['grade']) ? intval($_GET['grade']) : 0;
$page = max(1, dist_admin_page_param('page', 1));
$perPage = 20;

$where = '1=1';
if ($kw !== '') {
    $kwEsc = addslashes(substr($kw, 0, 32));
    if (ctype_digit($kw)) {
        $where .= " AND (u.uid=" . intval($kw) . " OR m.username LIKE '%{$kwEsc}%' OR u.invite_code LIKE '%{$kwEsc}%')";
    } else {
        $where .= " AND (m.username LIKE '%{$kwEsc}%' OR u.invite_code LIKE '%{$kwEsc}%')";
    }
}
if ($status >= 0 && $status <= 5) {
    $where .= " AND u.status={$status}";
}
if ($grade >= 1 && $grade <= 4) {
    $where .= " AND u.grade={$grade}";
}

if (isset($_GET['export'])) {
    $exportRows = array();
    $dsql->SetQuery("SELECT u.*,m.username FROM sea_dist_user u LEFT JOIN sea_member m ON m.id=u.uid WHERE {$where} ORDER BY u.uid DESC");
    $dsql->Execute('dist_users_export');
    while ($r = $dsql->GetArray('dist_users_export')) {
        $acct = dist_account_get($r['uid']);
        $team = dist_user_summary($r['uid']);
        $teamTotal = intval($team[1]) + intval($team[2]) + intval($team[3]);
        $exportRows[] = array(
            intval($r['uid']),
            $r['username'],
            $r['invite_code'],
            dist_user_status_name($r['status']),
            dist_grade_name($r['grade']),
            intval($team[1]) . ' / ' . $teamTotal,
            number_format(is_array($acct) ? floatval($acct['available_amount']) : 0, 2, '.', ''),
            number_format(is_array($acct) ? floatval($acct['pending_amount']) : 0, 2, '.', ''),
            intval($r['create_time']) > 0 ? date('Y-m-d H:i:s', intval($r['create_time'])) : ''
        );
    }
    dist_admin_export_csv('分销员_' . date('YmdHis'), array('UID', '用户名', '邀请码', '状态', '等级', '直属/团队', '可提现', '待结算', '开通时间'), $exportRows);
}

$total = 0;
$row = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_user u LEFT JOIN sea_member m ON m.id=u.uid WHERE {$where}");
if (is_array($row)) {
    $total = intval($row['dd']);
}
$totalPage = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPage);
$offset = ($page - 1) * $perPage;
$rows = array();
$dsql->SetQuery("SELECT u.*,m.username,m.points,m.state AS mstate FROM sea_dist_user u LEFT JOIN sea_member m ON m.id=u.uid WHERE {$where} ORDER BY u.uid DESC LIMIT {$offset},{$perPage}");
$dsql->Execute('dist_users');
while ($r = $dsql->GetArray('dist_users')) {
    $acct = dist_account_get($r['uid']);
    $team = dist_user_summary($r['uid']);
    $r['available'] = is_array($acct) ? floatval($acct['available_amount']) : 0;
    $r['pending'] = is_array($acct) ? floatval($acct['pending_amount']) : 0;
    $r['team1'] = $team[1];
    $r['team_total'] = $team[1] + $team[2] + $team[3];
    $rows[] = $r;
}

$baseQuery = array();
foreach (array('kw' => $kw, 'status' => $status >= 0 ? $status : '', 'grade' => $grade > 0 ? $grade : '') as $k => $v) {
    if ($v !== '') {
        $baseQuery[$k] = $v;
    }
}
$baseQueryStr = empty($baseQuery) ? '' : (http_build_query($baseQuery) . '&');

dist_admin_head('分销员管理', 'admin_dist_user.php');
?>
<div class="dist-panel">
  <h2>分销员列表</h2>
  <form method="get" class="dist-filter">
    <input type="text" name="kw" value="<?php echo dist_h($kw); ?>" placeholder="用户名 / UID / 邀请码">
    <select name="status">
      <option value="-1">全部状态</option>
      <?php for ($i = 0; $i <= 5; $i++): ?>
      <option value="<?php echo $i; ?>" <?php echo $status === $i ? 'selected' : ''; ?>><?php echo dist_h(dist_user_status_name($i)); ?></option>
      <?php endfor; ?>
    </select>
    <select name="grade">
      <option value="0">全部等级</option>
      <?php for ($i = 1; $i <= 4; $i++): ?>
      <option value="<?php echo $i; ?>" <?php echo $grade === $i ? 'selected' : ''; ?>><?php echo dist_h(dist_grade_name($i)); ?></option>
      <?php endfor; ?>
    </select>
    <button type="submit" class="dist-btn primary">筛选</button>
    <a href="admin_dist_user.php" class="dist-btn">重置</a>
    <a href="admin_dist_user.php?<?php echo dist_h($baseQueryStr); ?>export=1" class="dist-btn" onclick="return confirm('确认导出当前筛选结果（共 <?php echo $total; ?> 条）？');">导出Excel</a>
  </form>
  <table class="dist-table">
    <thead><tr><th>UID</th><th>用户名</th><th>邀请码</th><th>状态</th><th>等级</th><th>直属/团队</th><th>可提现</th><th>待结算</th><th>操作</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="9" class="dist-empty">暂无数据</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?php echo intval($r['uid']); ?></td>
        <td><?php echo dist_h($r['username']); ?></td>
        <td><?php echo dist_h($r['invite_code']); ?></td>
        <td><span class="dist-badge dist-b<?php echo intval($r['status']); ?>"><?php echo dist_h(dist_user_status_name($r['status'])); ?></span></td>
        <td><?php echo dist_h(dist_grade_name($r['grade'])); ?></td>
        <td><?php echo intval($r['team1'] ?? 0); ?> / <?php echo intval($r['team_total']); ?></td>
        <td><?php echo number_format($r['available'], 2); ?></td>
        <td><?php echo number_format($r['pending'], 2); ?></td>
        <td>
          <form method="post" class="dist-inline">
            <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
            <input type="hidden" name="uid" value="<?php echo intval($r['uid']); ?>">
            <?php if (intval($r['status']) === 1): ?>
              <button type="submit" name="do" value="freeze" class="dist-btn warn">冻结</button>
              <button type="submit" name="do" value="kick" class="dist-btn danger" onclick="return confirm('确认清退？清退后不能新增下级，已有关系停止计佣。');">清退</button>
            <?php else: ?>
              <button type="submit" name="do" value="unfreeze" class="dist-btn">恢复正常</button>
              <button type="submit" name="do" value="open" class="dist-btn primary">手动开通</button>
            <?php endif; ?>
            <button type="submit" name="do" value="reset_code" class="dist-btn" onclick="return confirm('重置后旧邀请码立即失效，确认？');">重置码</button>
          </form>
          <form method="post" class="dist-inline">
            <input type="hidden" name="csrf_token" value="<?php echo dist_h(dist_csrf()); ?>">
            <input type="hidden" name="uid" value="<?php echo intval($r['uid']); ?>">
            <input type="hidden" name="do" value="set_grade">
            <select name="grade" style="height:26px;">
              <?php for ($i = 1; $i <= 4; $i++): ?>
              <option value="<?php echo $i; ?>" <?php echo intval($r['grade']) === $i ? 'selected' : ''; ?>><?php echo dist_h(dist_grade_name($i)); ?></option>
              <?php endfor; ?>
            </select>
            <button type="submit" class="dist-btn">改级</button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php echo dist_admin_pager($total, $page, $perPage, 'admin_dist_user.php?' . $baseQueryStr); ?>
</div>
<?php dist_admin_foot(); ?>
