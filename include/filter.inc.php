<?php
if(!defined('sea_INC'))
{
	exit("Request Error!");
}
 
$magic_quotes_gpc = ini_get('magic_quotes_gpc');
function _FilterAll($fk, &$svar)
{
    global $cfg_notallowstr,$cfg_replacestr;
    if( is_array($svar) )
    {
        foreach($svar as $_k => $_v)
        {
            $svar[$_k] = _FilterAll($fk,$_v);
        }
    }
    else
    {
        if($cfg_notallowstr!='' && preg_match("#".$cfg_notallowstr."#i", $svar))
        {
            ShowMsg(" $fk has not allow words!",'-1');
            exit();
        }
        if($cfg_replacestr!='')
        {
            $svar = preg_replace('/'.$cfg_replacestr.'/i', "***", $svar);
        }
    }
    if (!$magic_quotes_gpc) {
        $svar = addslashes($svar);
    }
    return $svar;
}
 
/* 对_GET,_POST,_COOKIE进行过滤 */
/* 安全:注册为全局变量前先校验键名,禁止覆盖超全局数组/系统配置/内部变量 */
function _FilterCheckKey($_k)
{
    return (strlen($_k) > 0
        && !preg_match('/^(cfg_|GLOBALS|_GET|_POST|_COOKIE|_REQUEST|_SERVER|_FILES|_SESSION|_ENV|arrs1|arrs2|dsql|db|isMobile|needFilter|cronnextrun|timestamp|admincachefile|sea_INC|sea_ROOT|sea_DATA)/i', $_k)
        && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $_k));
}

foreach(Array('_GET','_POST','_COOKIE') as $_request)
{
    foreach($$_request as $_k => $_v)
    {
        if (_FilterCheckKey($_k))
        {
            ${$_k} = _FilterAll($_k,$_v);
        }
    }
}

function _Replace_Badword(&$var)
{
	global $cfg_notallowstr;
	
	$myarr = explode('|',$cfg_notallowstr);
	
	for($i=0;$i<count($myarr);$i++)
	{
		$var = str_replace($myarr[$i],"*",$var);
	}
	return $var;
}
/*foreach(Array('_GET','_POST','_COOKIE') as $_request)
{
	foreach($$_request as $_k => $_v)
	{
		${$_k} = _FilterAll($_k,$_v);
	}
}
*/?>