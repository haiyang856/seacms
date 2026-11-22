<?php
/**
 * 三级分销 - 审计日志
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

/**
 * 写入审计日志（只追加）
 */
function dist_log($uid, $action, $targetType = '', $targetId = '', $before = null, $after = null, $operatorType = 3, $operatorId = 0, $remark = '')
{
    global $dsql;
    if (!is_object($dsql)) {
        return false;
    }
    $uid = intval($uid);
    $action = addslashes(substr((string)$action, 0, 32));
    $targetType = addslashes(substr((string)$targetType, 0, 20));
    $targetId = addslashes(substr((string)$targetId, 0, 64));
    $beforeJson = $before === null ? 'NULL' : "'" . addslashes(json_encode($before, JSON_UNESCAPED_UNICODE)) . "'";
    $afterJson = $after === null ? 'NULL' : "'" . addslashes(json_encode($after, JSON_UNESCAPED_UNICODE)) . "'";
    $operatorType = intval($operatorType);
    $operatorId = intval($operatorId);
    $ip = addslashes(dist_client_ip());
    $ua = addslashes(dist_user_agent());
    $rid = addslashes(dist_request_id());
    $remark = addslashes(substr((string)$remark, 0, 255));
    $now = time();
    $sql = "INSERT INTO sea_dist_log (uid,action,target_type,target_id,before_data,after_data,operator_type,operator_id,ip,user_agent,request_id,remark,create_time)
        VALUES ({$uid},'{$action}','{$targetType}','{$targetId}',{$beforeJson},{$afterJson},{$operatorType},{$operatorId},'{$ip}','{$ua}','{$rid}','{$remark}',{$now})";
    return $dsql->ExecuteNoneQuery($sql);
}
