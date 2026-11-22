<?php
/**
 * 用户中心 - 邮箱激活 / 找回密码（游客流程）
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

//邮件激活
if ($member_mod == 'activate') {
    require_once(dirname(__DIR__, 2) . '/data/admin/smtp.php');
    if ($smtpreg == 'off') {
        showMsg("抱歉，系统已关闭邮件激活功能！", "../index.php", 0, 100000);
        exit();
    }
    $acode = addslashes((string)($_GET['acode'] ?? ''));
    $dsql->ExecuteNoneQuery("update `sea_member` set acode = 'y' where acode='$acode'");
    showMsg("恭喜，账户激活成功！", "login.php", 0, 3000);
    exit();
}

//找回密码 - 输入账号
if ($member_mod == 'repsw') {
    require_once(dirname(__DIR__, 2) . '/data/admin/smtp.php');
    if ($smtppsw == 'off') {
        showMsg("抱歉，系统已关闭密码找回功能！", "../index.php", 0, 100000);
        exit();
    }
    member_render('password.html');
    exit();
}

//找回密码 - 发送邮件
if ($member_mod == 'repsw2') {
    require_once(dirname(__DIR__, 2) . '/data/admin/smtp.php');
    if ($smtppsw == 'off') {
        showMsg("抱歉，系统已关闭密码找回功能！", "../index.php", 0, 100000);
        exit();
    }
    $repswname = trim((string)($_POST['repswname'] ?? ''));
    if ($repswname == '') {
        showMsg("请输入账户名称！", "-1", 0, 3000);
        exit();
    }
    $repswname = addslashes(cn_substr(RemoveXSS(stripslashes($repswname)), 60));
    $row = $dsql->GetOne("select * from sea_member where username='$repswname'");
    $row = is_array($row) ? $row : array();
    $repswemail = $row['email'] ?? '';
    $repswstate = $row['state'] ?? 0;
    if (empty($repswemail)) {
        showMsg("输入的账户名称不存在！", "-1", 0, 10000);
        exit();
    }
    if ($repswstate != 1) {
        showMsg("您的账户已被管理员禁用，禁止找回密码！", "-1", 0, 10000);
        exit();
    }
    $randtime = uniqid();
    $repswcode = md5($cfg_dbpwd . $cfg_dbname . $cfg_dbuser . $randtime);
    $dsql->ExecuteNoneQuery("update `sea_member` set repswcode = '$repswcode' where username= '$repswname'");

    $repswemailDb = addslashes($repswemail);
    $smtprmail = $repswemailDb;
    $smtprtitle = '【' . $cfg_webname . '】Email 找回密码操作邮件';
    $link = $cfg_basehost . '/member/index.php?mod=repsw3&repswcode=' . $repswcode . '&repswname=' . $row['username'];
    $smtprbody = '<strong>Email 找回密码操作邮件</strong><br><br>尊敬的：' . $row['username'] . '<br>这封信是由 ' . $cfg_webname . ' 发送的。<br>您收到这封邮件，是由于在 ' . $cfg_webname . ' 进行了找回密码操作。如果您并没有访问过 ' . $cfg_webname . '，或没有进行上述操作，请忽略这封邮件。您不需要退订或进行其他进一步的操作。<br><br>如果您是 ' . $cfg_webname . '的用户并且正在进行找回密码操作，我们需要对您的信息的有效性进行验证以避免密码被盗用。<br>您只需点击下面的链接即可重置您的密码：<br><a target="_blank" href="' . $link . '">' . $link . '</a><br>(如果上面不是链接形式，请将该地址手工粘贴到浏览器地址栏再访问)<br>此链接只允许访问一次！<br><br>感谢您的访问，祝您使用愉快！<br><br>此致<br>' . $cfg_webname . '管理团队.<br>' . $cfg_basehost . '<br>';

    require_once(sea_INC . "/class.phpmailer.php");
    $mail = new PHPMailer();
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->Host = $smtpserver;
    $mail->SMTPSecure = 'ssl';
    $mail->Port = intval($smtpserverport);
    $mail->CharSet = 'UTF-8';
    $mail->FromName = $smtpname;
    $mail->Username = $smtpuser;
    $mail->Password = $smtppass;
    $mail->From = $smtpusermail;
    $mail->isHTML(true);
    $mail->addAddress($smtprmail);
    $mail->Subject = "=?utf-8?B?" . base64_encode($smtprtitle) . "?=";
    $mail->Body = $smtprbody;
    $status = $mail->send();
    if ($status) {
        ShowMsg('申请成功!<br>一封验证邮件已发送到您的注册邮箱，请查收！', 'login.php', 0, 100000);
    } else {
        ShowMsg('抱歉！激活邮件发送失败，请联系客服解决此错误。', 'login.php', 0, 100000);
    }
    exit();
}

//找回密码 - 重置表单
if ($member_mod == 'repsw3') {
    require_once(dirname(__DIR__, 2) . '/data/admin/smtp.php');
    if ($smtppsw == 'off') {
        showMsg("抱歉，系统已关闭密码找回功能！", "../index.php", 0, 100000);
        exit();
    }
    $repswname = RemoveXSS(stripslashes((string)($_GET['repswname'] ?? '')));
    $repswname = addslashes(cn_substr($repswname, 60));
    $repswcode = RemoveXSS(stripslashes((string)($_GET['repswcode'] ?? '')));
    $repswcode = addslashes(cn_substr($repswcode, 60));
    if (empty($repswname)) {
        showMsg("授权码错误或已过期！", "../index.php", 0, 100000);
        exit();
    }
    if (empty($repswcode) OR $repswcode == "y") {
        showMsg("授权码错误或已过期！", "../index.php", 0, 100000);
        exit();
    }
    $row = $dsql->GetOne("select * from sea_member where username='$repswname'");
    $row = is_array($row) ? $row : array();
    if ($repswcode != ($row['repswcode'] ?? '')) {
        showMsg("授权码错误或已过期！", "../index.php", 0, 100000);
        exit();
    }
    $memberTpl->setVars(array(
        'repswname' => $repswname,
        'repswcode' => $repswcode
    ));
    member_render('password_reset.html');
    exit();
}

//找回密码 - 保存新密码
if ($member_mod == 'repsw4') {
    require_once(dirname(__DIR__, 2) . '/data/admin/smtp.php');
    if ($smtppsw == 'off') {
        showMsg("抱歉，系统已关闭密码找回功能！", "../index.php", 0, 100000);
        exit();
    }
    $repswname = addslashes(cn_substr(RemoveXSS(stripslashes((string)($_POST['repswname'] ?? ''))), 60));
    $repswcode = addslashes(cn_substr(RemoveXSS(stripslashes((string)($_POST['repswcode'] ?? ''))), 60));
    $repswnew1 = addslashes(cn_substr(RemoveXSS(stripslashes((string)($_POST['repswnew1'] ?? ''))), 60));
    $repswnew2 = addslashes(cn_substr(RemoveXSS(stripslashes((string)($_POST['repswnew2'] ?? ''))), 60));
    if ($repswnew1 != $repswnew2) {
        showMsg("两次输入密码不一致！", "-1", 0, 3000);
        exit();
    }
    if (empty($repswname)) {
        showMsg("授权码错误或已过期！", "../index.php", 0, 100000);
        exit();
    }
    if (empty($repswcode) OR $repswcode == "y") {
        showMsg("授权码错误或已过期！", "../index.php", 0, 100000);
        exit();
    }
    $row = $dsql->GetOne("select * from sea_member where username='$repswname'");
    $row = is_array($row) ? $row : array();
    if ($repswcode != ($row['repswcode'] ?? '')) {
        showMsg("授权码错误或已过期！", "../index.php", 0, 100000);
        exit();
    }
    $pwd = substr(md5($repswnew1), 5, 20);
    $dsql->ExecuteNoneQuery("update `sea_member` set password = '$pwd',repswcode = 'y' where username='$repswname'");
    ShowMsg('密码重置成功，请使用新密码登陆！', 'login.php');
    exit();
}
