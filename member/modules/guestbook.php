<?php
/**
 * 用户中心 - 我的留言
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

//删除留言
if ($member_action == 'del_gb') {
    $id = intval($id ?? 0);
    $name = addslashes(cn_substr(RemoveXSS(stripslashes($_SESSION['sea_user_name'])), 20));
    if ($id > 0) {
        $dsql->ExecuteNoneQuery("delete from sea_guestbook where uname='$name' and id=$id");
    }
    ShowMsg('恭喜，成功删除一条留言！', 'index.php?action=gb');
    exit();
}

$uname = $_SESSION['sea_user_name'];
$row = $dsql->GetOne("select count(id) as dd from sea_guestbook where uname='$uname'");
$total = is_array($row) ? intval($row['dd']) : 0;
$pageInfo = member_pagination($total, 20);
$offset = $pageInfo['offset'];
$pageSize = $pageInfo['pagesize'];

$rows = array();
$dsql->SetQuery("select * from sea_guestbook where uname='$uname' ORDER BY dtime DESC limit $offset,$pageSize");
$dsql->Execute('member_gb');
while ($item = $dsql->GetArray('member_gb')) {
    $rows[] = array(
        'id' => intval($item['id']),
        'title' => member_e($item['title']),
        'msg' => $item['msg'],
        'time' => MyDate('', $item['dtime']),
        'delurl' => member_url('del_gb', 'id=' . intval($item['id']))
    );
}
$memberTpl->setLoop('gb', $rows);
member_set_page($pageInfo, 'index.php?action=gb');
member_set('totalcount', $total);

member_render('gb.html');
