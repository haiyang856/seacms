<?php
/**
 * 用户中心 - 我的评论
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

//删除评论
if ($member_action == 'del_pl') {
    $id = intval($id ?? 0);
    $vid = intval($vid ?? 0);
    $itype = intval($itype ?? 0);
    $name = addslashes(cn_substr(RemoveXSS(stripslashes($_SESSION['sea_user_name'])), 20));
    if ($id > 0) {
        $dsql->ExecuteNoneQuery("delete from sea_comment where username='$name' and id=$id");
        delfile(sea_ROOT . "/data/cache/review/$itype/$vid.js");
    }
    ShowMsg('恭喜，成功删除一条评论！', 'index.php?action=VNc');
    exit();
}

$uname = $_SESSION['sea_user_name'];
$row = $dsql->GetOne("select count(id) as dd from sea_comment where username='$uname'");
$total = is_array($row) ? intval($row['dd']) : 0;
$pageInfo = member_pagination($total, 20);
$offset = $pageInfo['offset'];
$pageSize = $pageInfo['pagesize'];

$rows = array();
$dsql->SetQuery("select * from sea_comment where username='$uname' ORDER BY dtime DESC limit $offset,$pageSize");
$dsql->Execute('member_comment');
while ($item = $dsql->GetArray('member_comment')) {
    $content = member_content_link($item['v_id'], $item['m_type']);
    $rows[] = array(
        'id' => intval($item['id']),
        'title' => member_e($content['type'] . cn_substrR($content['name'], 30)),
        'link' => member_e($content['link']),
        'msg' => $item['msg'],
        'time' => MyDate('', $item['dtime']),
        'delurl' => member_url('del_pl', 'id=' . intval($item['id']) . '&vid=' . intval($item['v_id']) . '&itype=' . intval($item['m_type']))
    );
}
$memberTpl->setLoop('comment', $rows);
member_set_page($pageInfo, 'index.php?action=VNc');
member_set('totalcount', $total);

member_render('comment.html');
