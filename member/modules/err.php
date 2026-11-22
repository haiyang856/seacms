<?php
/**
 * 用户中心 - 我的报错
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

//删除报错
if ($member_action == 'del_err') {
    $id = intval($id ?? 0);
    $name = addslashes(cn_substr(RemoveXSS(stripslashes($_SESSION['sea_user_name'])), 20));
    if ($id > 0) {
        $dsql->ExecuteNoneQuery("delete from sea_erradd where author='$name' and id=$id");
    }
    ShowMsg('恭喜，成功删除一条报错！', 'index.php?action=err');
    exit();
}

$uname = $_SESSION['sea_user_name'];
$row = $dsql->GetOne("select count(id) as dd from sea_erradd where author='$uname'");
$total = is_array($row) ? intval($row['dd']) : 0;
$pageInfo = member_pagination($total, 20);
$offset = $pageInfo['offset'];
$pageSize = $pageInfo['pagesize'];

$rows = array();
$dsql->SetQuery("select * from sea_erradd where author='$uname' ORDER BY sendtime DESC limit $offset,$pageSize");
$dsql->Execute('member_err');
while ($item = $dsql->GetArray('member_err')) {
    $video = $dsql->GetOne("select tid,v_id,v_addtime,v_enname,v_name from sea_data where v_id=" . intval($item['vid']));
    if (!is_array($video)) {
        continue;
    }
    $link = getContentLink($video['tid'], $video['v_id'], "", date('Y-n', $video['v_addtime']), $video['v_enname']);
    $rows[] = array(
        'id' => intval($item['id']),
        'title' => member_e(cn_substrR($video['v_name'], 30)),
        'link' => member_e($link),
        'msg' => $item['errtxt'],
        'time' => MyDate('', $item['sendtime']),
        'replytext' => intval($item['ischeck']) == 1 ? '管理员回复：已处理，感谢您的支持！' : '',
        'delurl' => member_url('del_err', 'id=' . intval($item['id']))
    );
}
$memberTpl->setLoop('err', $rows);
member_set_page($pageInfo, 'index.php?action=err');
member_set('totalcount', $total);

member_render('err.html');
