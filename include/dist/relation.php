<?php
/**
 * 三级分销 - 关系绑定与祖先链
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

function dist_relation_get($uid)
{
    global $dsql;
    $uid = intval($uid);
    if ($uid <= 0) {
        return array();
    }
    $row = $dsql->GetOne("SELECT * FROM sea_dist_relation WHERE uid={$uid} limit 1");
    return is_array($row) ? $row : array();
}

/**
 * 检查目标节点是否有效（可参与分佣）
 */
function dist_node_valid($uid)
{
    global $dsql;
    $uid = intval($uid);
    if ($uid <= 0) {
        return false;
    }
    $row = $dsql->GetOne("SELECT u.status AS dstatus,m.state AS mstate FROM sea_dist_user u LEFT JOIN sea_member m ON m.id=u.uid WHERE u.uid={$uid} limit 1");
    if (!is_array($row)) {
        return false;
    }
    return intval($row['dstatus']) === 1 && intval($row['mstate']) === 1;
}

/**
 * 有效祖先链（最多 3 级，跳过失效节点）
 * @return array uid 列表，索引 0 为 L1
 */
function dist_ancestor_chain($uid)
{
    global $dsql;
    $uid = intval($uid);
    $chain = array();
    if ($uid <= 0) {
        return $chain;
    }
    $rel = dist_relation_get($uid);
    if (empty($rel) || intval($rel['status']) !== 1) {
        return $chain;
    }
    $next = intval($rel['p1_uid']);
    $guard = 0;
    while ($next > 0 && count($chain) < 3 && $guard < 12) {
        $guard++;
        $parentRel = dist_relation_get($next);
        if (dist_node_valid($next)) {
            $chain[] = $next;
        }
        if (empty($parentRel)) {
            break;
        }
        $next = intval($parentRel['p1_uid']);
    }
    return $chain;
}

/**
 * 绑定邀请人
 * @param int $uid 被邀请人
 * @param string $ref 邀请码
 * @param int $bindType 1注册 2登录后补绑 3后台补录
 * @param string $source pc/h5/miniapp/app/admin
 * @return array ok/message
 */
function dist_bind($uid, $ref, $bindType = 1, $source = 'pc')
{
    global $dsql;
    $uid = intval($uid);
    $ref = trim((string)$ref);
    $bindType = intval($bindType);
    $source = preg_replace('/[^a-z]/', '', strtolower((string)$source));
    if ($source === '') {
        $source = 'pc';
    }
    if (!dist_enabled()) {
        return array('ok' => false, 'message' => '分销功能未开启');
    }
    if ($uid <= 0 || $ref === '') {
        return array('ok' => false, 'message' => '参数不完整');
    }
    if (!preg_match('/^[A-Za-z0-9]{4,16}$/', $ref)) {
        return array('ok' => false, 'message' => '邀请码格式不正确');
    }
    $inviter = $dsql->GetOne("SELECT u.*,m.username,m.state AS mstate FROM sea_dist_user u LEFT JOIN sea_member m ON m.id=u.uid WHERE u.invite_code='" . addslashes($ref) . "' limit 1");
    if (!is_array($inviter)) {
        return array('ok' => false, 'message' => '邀请码不存在');
    }
    $inviterUid = intval($inviter['uid']);
    if ($inviterUid === $uid) {
        return array('ok' => false, 'message' => '不能绑定自己');
    }
    if (intval($inviter['status']) !== 1) {
        return array('ok' => false, 'message' => '邀请人身份不可用');
    }
    if (intval($inviter['mstate']) !== 1) {
        return array('ok' => false, 'message' => '邀请人账号状态异常');
    }
    $member = $dsql->GetOne("SELECT id,state,regtime FROM sea_member WHERE id={$uid} limit 1");
    if (!is_array($member) || intval($member['state']) !== 1) {
        return array('ok' => false, 'message' => '账号状态异常，无法绑定');
    }
    $existing = dist_relation_get($uid);
    if (!empty($existing) && intval($existing['status']) === 1) {
        return array('ok' => false, 'message' => '您已有推荐人，无法重复绑定');
    }
    // 支付即锁定：已有佣金订单不可绑定
    $paid = $dsql->GetOne("SELECT id FROM sea_dist_order WHERE buyer_uid={$uid} limit 1");
    if (is_array($paid)) {
        return array('ok' => false, 'message' => '您已产生有效消费，无法绑定推荐人');
    }
    // 补绑冷静期
    if ($bindType === 2) {
        $days = intval(dist_cfg('register_days', 7));
        if ($days > 0 && intval($member['regtime']) > 0 && intval($member['regtime']) < time() - $days * 86400) {
            return array('ok' => false, 'message' => '注册已超过 ' . $days . ' 天，无法补绑');
        }
        $direct = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_relation WHERE parent_uid={$uid} AND status=1");
        if (is_array($direct) && intval($direct['dd']) > 0) {
            return array('ok' => false, 'message' => '您已有下级，无法补绑');
        }
    }
    // 防环：邀请人的祖先链中不得包含自己
    $guard = 0;
    $cursor = $inviterUid;
    while ($cursor > 0 && $guard < 12) {
        $guard++;
        if ($cursor === $uid) {
            return array('ok' => false, 'message' => '关系冲突，无法绑定');
        }
        $r = dist_relation_get($cursor);
        if (empty($r)) {
            break;
        }
        $cursor = intval($r['p1_uid']);
    }
    // 直推人数上限
    $maxDirect = intval(dist_cfg('max_direct', 5000));
    $cnt = $dsql->GetOne("SELECT COUNT(*) AS dd FROM sea_dist_relation WHERE parent_uid={$inviterUid} AND status=1");
    if ($maxDirect > 0 && is_array($cnt) && intval($cnt['dd']) >= $maxDirect) {
        return array('ok' => false, 'message' => '邀请人下级数量已达上限');
    }

    $invRel = dist_relation_get($inviterUid);
    $p2 = is_array($invRel) ? intval($invRel['p1_uid']) : 0;
    $p3 = is_array($invRel) ? intval($invRel['p2_uid']) : 0;
    $path = '';
    $depth = 0;
    if (!is_array($invRel)) {
        $path = (string)$inviterUid;
        $depth = 1;
    } else {
        $path = trim((string)$invRel['path'], ',');
        $path = $path === '' ? (string)$inviterUid : $path . ',' . $inviterUid;
        $depth = intval($invRel['depth']) + 1;
    }
    if (strlen($path) > 250) {
        $path = substr($path, -250);
        $path = ltrim($path, ',');
    }

    $now = time();
    $refEsc = addslashes($ref);
    $sourceEsc = addslashes($source);
    $pathEsc = addslashes($path);
    $bindType = intval($bindType);
    $sql = "INSERT INTO sea_dist_relation (uid,parent_uid,p1_uid,p2_uid,p3_uid,depth,path,bind_type,bind_source,bind_ref,status,bind_time,update_time,close_reason)
        VALUES ({$uid},{$inviterUid},{$inviterUid},{$p2},{$p3},{$depth},'{$pathEsc}',{$bindType},'{$sourceEsc}','{$refEsc}',1,{$now},{$now},'')";
    $done = $dsql->ExecuteNoneQuery($sql);
    if (!$done) {
        // 唯一键冲突视为已绑定
        $fresh = dist_relation_get($uid);
        if (!empty($fresh) && intval($fresh['status']) === 1) {
            return array('ok' => false, 'message' => '您已有推荐人，无法重复绑定');
        }
        return array('ok' => false, 'message' => '绑定失败，请稍后重试');
    }
    dist_log($uid, 'bind', 'relation', (string)$uid, null, array(
        'parent_uid' => $inviterUid,
        'p1_uid' => $inviterUid,
        'p2_uid' => $p2,
        'p3_uid' => $p3,
        'ref' => $ref,
        'bind_type' => $bindType,
        'source' => $source
    ), $bindType === 3 ? 2 : 1, 0, '建立分销关系');
    return array('ok' => true, 'message' => '绑定成功');
}

/**
 * 从请求/Cookie 中提取邀请码
 */
function dist_ref_from_request()
{
    $ref = '';
    if (!empty($_GET['ref']) && is_string($_GET['ref'])) {
        $ref = $_GET['ref'];
    } elseif (!empty($_POST['ref']) && is_string($_POST['ref'])) {
        $ref = $_POST['ref'];
    } elseif (!empty($_COOKIE['sea_dist_ref']) && is_string($_COOKIE['sea_dist_ref'])) {
        $ref = $_COOKIE['sea_dist_ref'];
    }
    return preg_replace('/[^A-Za-z0-9]/', '', (string)$ref);
}
