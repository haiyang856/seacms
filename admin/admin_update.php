<?php 
//die('<p style="font-size:18px;">暂不开放在线升级功能！</p>');
@set_time_limit(0);
require(dirname(__FILE__).'/config.php');
CheckPurview('');

//获取当前管理后台实际文件夹名
$adminDir = basename(str_replace('\\','/',dirname(__FILE__)));
$adminDir = $adminDir ? $adminDir : 'admin';

//获取当前系统的版本号
$version = file_get_contents(sea_ADMIN.'/version.txt');
if(empty($version)){
	$version = '0.0.0';
}

//配置升级项目地址
$updateUrl = 'https://github.com/haiyang856/seacms/releases/latest';	

//从升级项目中的 data/admin/ver.txt 获取最新版本号
$latestVersion = file_get_contents($updateUrl.'/data/admin/ver.txt');
if(empty($latestVersion)){
	$latestVersion = '0.0.0';
}

die($latestVersion);

//调用 GitHUB 仓库实现升级功能






?>