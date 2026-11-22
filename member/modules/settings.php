<?php
/**
 * 用户中心 - 修改个人信息
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

if (!function_exists('ImageResize2')) {
    function ImageResize2($srcFile, $toW, $toH, $toFile = "")
    {
        if ($toFile == "") {
            $toFile = $srcFile;
        }
        $srcInfo = getimagesize($srcFile);
        if (!$srcInfo) {
            return false;
        }
        // 大图解码需要大量内存，先提升上限并校验估算值，避免内存耗尽导致致命错误
        $need = intval($srcInfo[0]) * intval($srcInfo[1]) * 8 + 16 * 1048576;
        @ini_set('memory_limit', (string)max(268435456, $need));
        $memLimit = member_memory_limit();
        if ($memLimit > 0 && $need > $memLimit - memory_get_usage(true)) {
            return false;
        }
        switch ($srcInfo[2]) {
            case 1:
                $im = imagecreatefromgif($srcFile);
                break;
            case 2:
                $im = imagecreatefromjpeg($srcFile);
                break;
            case 3:
                $im = imagecreatefrompng($srcFile);
                break;
            case 18:
                $im = imagecreatefromwebp($srcFile);
                break;
            case 6:
                $im = imagecreatefromwbmp($srcFile);
                break;
            default:
                return false;
        }
        if (!$im) {
            return false;
        }
        $srcW = ImageSX($im);
        $srcH = ImageSY($im);
        if ($srcW <= $toW && $srcH <= $toH) {
            imagedestroy($im);
            return true;
        }
        $toWH = $toW / $toH;
        $srcWH = $srcW / $srcH;
        if ($toWH <= $srcWH) {
            $ftoW = $toW;
            $ftoH = $ftoW * ($srcH / $srcW);
        } else {
            $ftoH = $toH;
            $ftoW = $ftoH * ($srcW / $srcH);
        }
        if ($srcW > $toW || $srcH > $toH) {
            if (function_exists("imagecreatetruecolor")) {
                @$ni = imagecreatetruecolor($ftoW, $ftoH);
                if ($ni) {
                    imagecopyresampled($ni, $im, 0, 0, 0, 0, $ftoW, $ftoH, $srcW, $srcH);
                } else {
                    $ni = imagecreate($ftoW, $ftoH);
                    imagecopyresized($ni, $im, 0, 0, 0, 0, $ftoW, $ftoH, $srcW, $srcH);
                }
            } else {
                $ni = imagecreate($ftoW, $ftoH);
                imagecopyresized($ni, $im, 0, 0, 0, 0, $ftoW, $ftoH, $srcW, $srcH);
            }
            switch ($srcInfo[2]) {
                case 1:
                    imagegif($ni, $toFile);
                    break;
                case 2:
                    imagejpeg($ni, $toFile, 99);
                    break;
                case 3:
                    imagepng($ni, $toFile);
                    break;
                case 18:
                    imagewebp($ni, $toFile);
                    break;
                case 6:
                    imagebmp($ni, $toFile);
                    break;
                default:
                    imagedestroy($ni);
                    imagedestroy($im);
                    return false;
            }
            imagedestroy($ni);
        }
        imagedestroy($im);
        return true;
    }
}

//读取 PHP 内存上限（字节），-1 表示不限制
if (!function_exists('member_memory_limit')) {
    function member_memory_limit()
    {
        $val = trim((string)ini_get('memory_limit'));
        if ($val === '' || $val === '-1') {
            return -1;
        }
        $unit = strtolower(substr($val, -1));
        $num = intval($val);
        if ($unit === 'g') {
            $num *= 1073741824;
        } elseif ($unit === 'm') {
            $num *= 1048576;
        } elseif ($unit === 'k') {
            $num *= 1024;
        }
        return $num;
    }
}

//修改个人信息
if ($member_action == 'chgpwdsubmit') {
    $newpwd = trim((string)($_POST['newpwd'] ?? ''));
    $newpwd2 = trim((string)($_POST['newpwd2'] ?? ''));
    $oldpwd = (string)($_POST['oldpwd'] ?? '');
    $email = trim((string)($_POST['email'] ?? ''));
    $nickname = trim((string)($_POST['nickname'] ?? ''));

    if ($newpwd !== $newpwd2) {
        ShowMsg('两次输入密码不一致', '-1');
        exit();
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
        ShowMsg('请输入正确的邮箱地址', '-1');
        exit();
    }
    //旧密码验证
    $spwd = $dsql->GetOne("SELECT * FROM sea_member where id=$uid");
    $spwd = is_array($spwd) ? $spwd : array();
    if (substr(md5($oldpwd), 5, 20) != ($spwd['password'] ?? '')) {
        ShowMsg('旧密码错误', '-1');
        exit();
    }

    $path2 = '';
    $oldpic = (string)($_POST['oldpic'] ?? '');
    if (isset($_FILES['image']['name']) && $_FILES['image']['name'] != "" && $cfg_upic == '1') {
        $file_size = intval($_FILES['image']['size']);
        $file_tmp = $_FILES['image']['tmp_name'];
        $name_arr = explode('.', $_FILES['image']['name']);
        $file_ext = strtolower(end($name_arr));
        $extensions = array("jpeg", "jpg", "png", "gif", "bmp", "webp");
        if ($file_size > 2048000) {
            ShowMsg('头像文件大小不能超过2M', '-1');
            exit;
        }
        if (in_array($file_ext, $extensions) === false) {
            ShowMsg('头像文件必须是图片', '-1');
            exit;
        }
        $imgInfo = @getimagesize($file_tmp);
        if (!$imgInfo || empty($imgInfo[0]) || empty($imgInfo[1])) {
            ShowMsg('头像文件必须是图片', '-1');
            exit;
        }
        // 提前提升内存上限并校验图片像素，避免解码超大图片时内存耗尽
        @ini_set('memory_limit', '256M');
        $memLimit = member_memory_limit();
        $estimated = intval($imgInfo[0]) * intval($imgInfo[1]) * 8;
        if ($memLimit > 0 && $estimated > $memLimit - memory_get_usage(true)) {
            ShowMsg('图片尺寸过大，请压缩后再上传', '-1');
            exit;
        }
        $ym = date('Y-n', time());
        $dir = "uploads/user/" . $ym . "/";
        $path2 = $dir . $uid . "_" . time() . "." . $file_ext;
        $updir = sea_ROOT . "/" . $dir;
        if (!is_dir($updir)) {
            @mkdir($updir, 0777, true);
        }
        if (!move_uploaded_file($file_tmp, sea_ROOT . "/" . $path2)) {
            $path2 = "";
        }
        if ($path2 != "") {
            if (!ImageResize2(sea_ROOT . "/" . $path2, 100, 100, "")) {
                @unlink(sea_ROOT . "/" . $path2);
                ShowMsg('头像处理失败，请更换图片后重试', '-1');
                exit;
            }
            if ($oldpic != 'uploads/user/a.png' && $oldpic != '') {
                @unlink(sea_ROOT . "/" . $oldpic);
            }
        }
    }
    if ($path2 == "") {
        $path2 = $oldpic;
    }

    $nickname = RemoveXSS(stripslashes($nickname));
    $nickname = addslashes(cn_substr($nickname, 60));
    $emailDb = addslashes(cn_substr(RemoveXSS(stripslashes($email)), 100));
    $pathDb = addslashes($path2);

    $pwd = empty($newpwd) ? $spwd['password'] : substr(md5($newpwd), 5, 20);
    $dsql->ExecuteNoneQuery("update `sea_member` set password = '$pwd',email = '$emailDb',nickname = '$nickname',pic='$pathDb' where id= '$uid'");
    ShowMsg('个人信息修改成功', 'index.php?action=settings');
    exit();
}

$memberTpl->setVars(array(
    'oldpic' => member_e($memberRow['pic'] ?? ''),
    'upichidden' => $cfg_upic == '1' ? '' : 'display:none;'
));

member_render('settings.html');
