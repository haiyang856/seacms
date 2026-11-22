<?php
/**
 * 用户中心 - 我的收藏
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

//取消单个收藏
if ($member_action == 'cancelfav') {
    $id = intval($id ?? 0);
    if ($id > 0) {
        $dsql->ExecuteNoneQuery("delete from sea_favorite where id=$id and uid=$uid");
    }
    ShowMsg('已取消收藏', 'index.php?action=favorite');
    exit();
}

//批量取消收藏
if ($member_action == 'cancelfavs') {
    $fid = isset($_POST['fid']) && is_array($_POST['fid']) ? $_POST['fid'] : array();
    if (empty($fid)) {
        ShowMsg("请选择要取消收藏的视频", 'index.php?action=favorite');
        exit();
    }
    $ids = array();
    foreach ($fid as $oneId) {
        $oneId = intval($oneId);
        if ($oneId > 0) {
            $ids[] = $oneId;
        }
    }
    if (!empty($ids)) {
        $dsql->ExecuteNoneQuery("delete from sea_favorite where uid=$uid and id in (" . implode(',', $ids) . ")");
    }
    ShowMsg('已取消选中的收藏', 'index.php?action=favorite');
    exit();
}

$row = $dsql->GetOne("select count(id) as dd from sea_favorite where uid=$uid");
$total = is_array($row) ? intval($row['dd']) : 0;
$pageInfo = member_pagination($total, 20);
$page = $pageInfo['page'];
$offset = $pageInfo['offset'];
$pageSize = $pageInfo['pagesize'];

$rows = array();
$dsql->SetQuery("select * from sea_favorite where uid=$uid ORDER BY kptime DESC limit $offset,$pageSize");
$dsql->Execute('member_favorite');
while ($item = $dsql->GetArray('member_favorite')) {
    $video = $dsql->GetOne("select v_hit,v_state,v_pic,v_name,v_enname,v_note,v_addtime,tid from sea_data where v_id=" . intval($item['vid']));
    if (!is_array($video)) {
        continue;
    }
    $link = getContentLink($video['tid'], $item['vid'], "", date('Y-n', $video['v_addtime']), $video['v_enname']);
    $rows[] = array(
        'id' => intval($item['id']),
        'vid' => intval($item['vid']),
        'name' => member_e($video['v_name']),
        'link' => member_e($link),
        'pic' => member_e($video['v_pic']),
        'hit' => intval($video['v_hit']),
        'state' => member_e($video['v_state']),
        'note' => member_e($video['v_note']),
        'time' => MyDate('', $item['kptime']),
        'delurl' => member_url('cancelfav', 'id=' . intval($item['id']))
    );
}
$memberTpl->setLoop('favorite', $rows);
member_set_page($pageInfo, 'index.php?action=favorite');
member_set('listcount', count($rows));
member_set('totalcount', $total);

member_render('favorite.html');
