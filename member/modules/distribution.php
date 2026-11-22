<?php
/**
 * 用户中心 - 三级分销中心
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}
require_once(sea_ROOT . '/include/dist/bootstrap.php');

if (!dist_enabled()) {
    ShowMsg('分销功能暂未开启', 'index.php');
    exit;
}

$fxDo = isset($_GET['do']) ? trim((string)$_GET['do']) : 'overview';
if (!in_array($fxDo, array('overview', 'team', 'commission', 'withdraw', 'material'), true)) {
    $fxDo = 'overview';
}
$fxBase = 'index.php?action=fx';

// 处理提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    member_csrf_check();
    $op = isset($_POST['op']) ? trim((string)$_POST['op']) : '';

    if ($op === 'bind') {
        $ref = isset($_POST['ref']) ? trim((string)$_POST['ref']) : '';
        $res = dist_bind($uid, $ref, 2, (isset($GLOBALS['isMobile']) && intval($GLOBALS['isMobile']) === 1) ? 'h5' : 'pc');
        ShowMsg($res['message'], $fxBase);
        exit;
    }

    if ($op === 'withdraw') {
        $amount = isset($_POST['amount']) ? dist_parse_amount($_POST['amount']) : false;
        $channel = isset($_POST['channel']) ? trim((string)$_POST['channel']) : 'manual';
        $account = isset($_POST['account_info']) ? trim((string)$_POST['account_info']) : '';
        $account = RemoveXSS($account);
        if ($amount === false) {
            ShowMsg('请输入正确的提现金额', $fxBase . '&do=withdraw');
            exit;
        }
        $res = dist_withdraw_apply($uid, $amount, $channel, $account);
        ShowMsg($res['message'], $fxBase . '&do=withdraw');
        exit;
    }

    if ($op === 'convert') {
        $amount = isset($_POST['amount']) ? dist_parse_amount($_POST['amount']) : false;
        if ($amount === false) {
            ShowMsg('请输入正确的兑换金额', $fxBase . '&do=withdraw');
            exit;
        }
        $res = dist_convert_points($uid, $amount);
        ShowMsg($res['message'], $fxBase . '&do=withdraw');
        exit;
    }

    if ($op === 'cancel_withdraw') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $res = dist_withdraw_cancel($id, $uid);
        ShowMsg($res['message'], $fxBase . '&do=withdraw');
        exit;
    }

    ShowMsg('未知操作', $fxBase);
    exit;
}

// 尝试自动开通（未达标时会返回原因）
$fxActivate = dist_try_activate($uid);
$fxUser = dist_user_get($uid);
$fxActive = (!empty($fxUser) && intval($fxUser['status']) === 1);

if (!$fxActive) {
    $fxOverview = dist_member_overview($uid);
    $fxRelation = dist_relation_get($uid);
    $parentName = '';
    if (!empty($fxRelation) && intval($fxRelation['parent_uid']) > 0) {
        $parent = $dsql->GetOne("SELECT username FROM sea_member WHERE id=" . intval($fxRelation['parent_uid']));
        $parentName = is_array($parent) ? dist_mask_name($parent['username']) : '';
    }
    $fxStatus = !empty($fxUser) ? intval($fxUser['status']) : 0;
    $fxWaitTitle = '暂未开通分销身份';
    if ($fxStatus === 2) {
        $fxWaitTitle = '分销身份已冻结';
    } elseif ($fxStatus === 3) {
        $fxWaitTitle = '分销申请未通过';
    } elseif ($fxStatus === 4) {
        $fxWaitTitle = '分销身份已清退';
    } elseif ($fxStatus === 5) {
        $fxWaitTitle = '分销身份已注销';
    }
    $fxCanBind = false;
    if ($parentName === '') {
        $fxHasPaid = $dsql->GetOne("SELECT id FROM sea_dist_order WHERE buyer_uid={$uid} limit 1");
        $fxHasChild = $dsql->GetOne("SELECT uid FROM sea_dist_relation WHERE parent_uid={$uid} AND status=1 limit 1");
        $fxRegDays = intval(dist_cfg('register_days', 7));
        $fxRegTime = intval($memberRow['regtime'] ?? 0);
        $fxWithinReg = ($fxRegDays <= 0) || ($fxRegTime > 0 && $fxRegTime > time() - $fxRegDays * 86400);
        if (!is_array($fxHasPaid) && !is_array($fxHasChild) && $fxWithinReg) {
            $fxCanBind = true;
        }
    }
    member_sets(array(
        'fx_status' => dist_user_status_name(!empty($fxUser) ? $fxUser['status'] : 0),
        'fx_wait_title' => $fxWaitTitle,
        'fx_reason' => member_e($fxActivate['message']),
        'fx_consume' => $fxOverview['consume'],
        'fx_threshold' => $fxOverview['threshold'],
        'fx_progress' => $fxOverview['progress'],
        'fx_parent' => member_e($parentName),
        'fx_has_parent' => $parentName !== '' ? '1' : '0',
        'fx_can_bind' => $fxCanBind ? '1' : '0',
        'fx_register_days' => intval(dist_cfg('register_days', 7)),
        'fx_email' => member_e($memberRow['email'] ?? ''),
        'fx_curr' => 'fx'
    ));
    member_render('distribution_wait.html');
    exit;
}

// 已开通：基础数据
$fxOverview = dist_member_overview($uid);
$fxCode = (string)$fxUser['invite_code'];
$fxBaseUrl = rtrim((string)($GLOBALS['cfg_basehost'] ?? ''), '/');
if ($fxBaseUrl === '') {
    $fxBaseUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . (string)($_SERVER['HTTP_HOST'] ?? '');
}
$fxLink = $fxBaseUrl . '/?ref=' . rawurlencode($fxCode);
$fxRelation = dist_relation_get($uid);
$parentName = '';
if (!empty($fxRelation) && intval($fxRelation['parent_uid']) > 0) {
    $parent = $dsql->GetOne("SELECT username FROM sea_member WHERE id=" . intval($fxRelation['parent_uid']));
    $parentName = is_array($parent) ? dist_mask_name($parent['username']) : '';
}
$fxShare = '我在' . (string)($GLOBALS['cfg_webname'] ?? '本站') . '发现了不错的影视内容，注册即可观看：' . $fxLink;

// 绑定入口可见性：已有上级、已产生计佣订单、已有下级或超过补绑期限时不再展示
$fxCanBind = false;
if ($parentName === '') {
    $fxHasPaid = $dsql->GetOne("SELECT id FROM sea_dist_order WHERE buyer_uid={$uid} limit 1");
    $fxHasChild = $dsql->GetOne("SELECT uid FROM sea_dist_relation WHERE parent_uid={$uid} AND status=1 limit 1");
    $fxRegDays = intval(dist_cfg('register_days', 7));
    $fxRegTime = intval($memberRow['regtime'] ?? 0);
    $fxWithinReg = ($fxRegDays <= 0) || ($fxRegTime > 0 && $fxRegTime > time() - $fxRegDays * 86400);
    if (!is_array($fxHasPaid) && !is_array($fxHasChild) && $fxWithinReg) {
        $fxCanBind = true;
    }
}
$fxObserveDays = intval(dist_cfg('observe_days', 7));
$fxNewbieObserveDays = max($fxObserveDays, intval(dist_cfg('risk_newbie_observe_days', 0)));

member_sets(array(
    'fx_curr' => 'fx',
    'fx_tab' => $fxDo,
    'fx_status' => dist_user_status_name($fxUser['status']),
    'fx_grade' => dist_grade_name($fxUser['grade']),
    'fx_invite_code' => member_e($fxCode),
    'fx_invite_link' => member_e($fxLink),
    'fx_share_text' => member_e($fxShare),
    'fx_parent' => member_e($parentName),
    'fx_has_parent' => $parentName !== '' ? '1' : '0',
    'fx_can_bind' => $fxCanBind ? '1' : '0',
    'fx_observe_days' => $fxObserveDays,
    'fx_newbie_observe_days' => $fxNewbieObserveDays,
    'fx_register_days' => intval(dist_cfg('register_days', 7)),
    'fx_total' => number_format(floatval($fxOverview['account']['total_amount']), 2, '.', ''),
    'fx_pending' => number_format(floatval($fxOverview['account']['pending_amount']), 2, '.', ''),
    'fx_available' => number_format(floatval($fxOverview['account']['available_amount']), 2, '.', ''),
    'fx_frozen' => number_format(floatval($fxOverview['account']['frozen_amount']), 2, '.', ''),
    'fx_withdrawn' => number_format(floatval($fxOverview['account']['withdrawn_amount']), 2, '.', ''),
    'fx_negative' => number_format(floatval($fxOverview['account']['negative_amount']), 2, '.', ''),
    'fx_points_converted' => number_format(floatval($fxOverview['account']['points_converted']), 2, '.', ''),
    'fx_team1' => intval($fxOverview['team'][1]),
    'fx_team2' => intval($fxOverview['team'][2]),
    'fx_team3' => intval($fxOverview['team'][3]),
    'fx_min_withdraw' => number_format(floatval(dist_cfg('min_withdraw', 100)), 2, '.', ''),
    'fx_exchange_rate' => intval(dist_cfg('exchange_rate', 100))
));

if ($fxDo === 'team') {
    $level = isset($_GET['level']) ? intval($_GET['level']) : 1;
    if (!in_array($level, array(1, 2, 3), true)) {
        $level = 1;
    }
    $pageInfo = member_pagination(0, 20);
    $data = dist_team_list($uid, $level, $pageInfo['offset'], $pageInfo['pagesize']);
    // 重新按实际总数分页
    $pageInfo = member_pagination($data['total'], 20);
    $data = dist_team_list($uid, $level, $pageInfo['offset'], $pageInfo['pagesize']);
    member_set_page($pageInfo, $fxBase . '&do=team&level=' . $level);
    $memberTpl->setLoop('fx_team', $data['rows']);
    member_sets(array(
        'fx_level' => $level,
        'fx_team_total' => $data['total']
    ));
    member_render('distribution_team.html');
    exit;
}

if ($fxDo === 'commission') {
    $status = isset($_GET['st']) ? intval($_GET['st']) : -1;
    if ($status < -1 || $status > 5) {
        $status = -1;
    }
    $pageInfo = member_pagination(0, 20);
    $data = dist_commission_list($uid, $status, $pageInfo['offset'], $pageInfo['pagesize']);
    $pageInfo = member_pagination($data['total'], 20);
    $data = dist_commission_list($uid, $status, $pageInfo['offset'], $pageInfo['pagesize']);
    member_set_page($pageInfo, $fxBase . '&do=commission' . ($status >= 0 ? '&st=' . $status : ''));
    $memberTpl->setLoop('fx_commission', $data['rows']);
    member_sets(array(
        'fx_st' => $status,
        'fx_commission_total' => $data['total']
    ));
    member_render('distribution_commission.html');
    exit;
}

if ($fxDo === 'withdraw') {
    $pageInfo = member_pagination(0, 20);
    $data = dist_withdraw_list($uid, $pageInfo['offset'], $pageInfo['pagesize']);
    $pageInfo = member_pagination($data['total'], 20);
    $data = dist_withdraw_list($uid, $pageInfo['offset'], $pageInfo['pagesize']);
    $csrfToken = member_csrf_token();
    foreach ($data['rows'] as $wk => $wv) {
        if (!empty($wv['can_cancel'])) {
            $data['rows'][$wk]['cancel_html'] = '<form method="post" action="?action=fx&do=withdraw" onsubmit="return confirm(\'确认撤销该提现申请？\');">'
                . '<input type="hidden" name="csrf_token" value="' . $csrfToken . '">'
                . '<input type="hidden" name="op" value="cancel_withdraw">'
                . '<input type="hidden" name="id" value="' . intval($wv['id']) . '">'
                . '<button type="submit" class="fx-linkbtn">撤销</button></form>';
        } else {
            $data['rows'][$wk]['cancel_html'] = '';
        }
    }
    member_set_page($pageInfo, $fxBase . '&do=withdraw');
    $memberTpl->setLoop('fx_withdraw', $data['rows']);
    member_sets(array('fx_withdraw_total' => $data['total']));
    member_render('distribution_withdraw.html');
    exit;
}

if ($fxDo === 'material') {
    member_render('distribution_material.html');
    exit;
}

member_render('distribution.html');
