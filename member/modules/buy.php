<?php
/**
 * 用户中心 - 视频购买记录
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

$row = $dsql->GetOne("select count(id) as dd from sea_buy where uid=$uid");
$total = is_array($row) ? intval($row['dd']) : 0;
$pageInfo = member_pagination($total, 20);
$offset = $pageInfo['offset'];
$pageSize = $pageInfo['pagesize'];

$rows = array();
$dsql->SetQuery("select * from sea_buy where uid=$uid group by vid ORDER BY kptime DESC limit $offset,$pageSize");
$dsql->Execute('member_buy');
while ($item = $dsql->GetArray('member_buy')) {
    $video = $dsql->GetOne("select v_hit,v_state,v_pic,v_name,v_enname,v_note,v_addtime,tid from sea_data where v_id=" . intval($item['vid']));
    if (!is_array($video)) {
        continue;
    }
    $link = getContentLink($video['tid'], $item['vid'], "", date('Y-n', $video['v_addtime']), $video['v_enname']);
    $rows[] = array(
        'vid' => intval($item['vid']),
        'name' => member_e($video['v_name']),
        'link' => member_e($link),
        'pic' => member_e($video['v_pic']),
        'hit' => intval($video['v_hit']),
        'state' => member_e($video['v_state']),
        'note' => member_e($video['v_note']),
        'time' => MyDate('', $item['kptime'])
    );
}
$memberTpl->setLoop('buy', $rows);
member_set_page($pageInfo, 'index.php?action=buy');
member_set('totalcount', $total);

member_render('buy.html');
