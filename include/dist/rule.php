<?php
/**
 * 三级分销 - 分佣规则匹配与费率计算
 */
if (!defined('sea_INC')) {
    exit('Request Error!');
}

/**
 * 查找当前生效规则
 * @param int $scopeType 1全局 2分类 3单品 4会员套餐 5充值渠道
 * @param int $scopeId
 * @return array
 */
function dist_rule_current($scopeType, $scopeId, $now = 0)
{
    global $dsql;
    $scopeType = intval($scopeType);
    $scopeId = intval($scopeId);
    $now = $now > 0 ? intval($now) : time();
    $sql = "SELECT * FROM sea_dist_rule WHERE status=1 AND scope_type={$scopeType} AND scope_id={$scopeId}
        AND (effective_time=0 OR effective_time<={$now}) AND (expire_time=0 OR expire_time>{$now})
        ORDER BY version DESC,id DESC limit 1";
    $row = $dsql->GetOne($sql);
    return is_array($row) ? $row : array();
}

/**
 * 等级系数（万分之一）
 */
function dist_grade_factor_bp($grade)
{
    global $dsql;
    $grade = intval($grade);
    if ($grade < 1) {
        $grade = 1;
    }
    if ($grade > 4) {
        $grade = 4;
    }
    $row = dist_rule_current(6, $grade);
    if (!empty($row)) {
        $bp = dist_bp($row['grade_factor']);
        if ($bp > 0) {
            return $bp;
        }
    }
    return dist_bp(dist_cfg('grade_factor_' . $grade, 1));
}

/**
 * 计算三级费率
 * @param array $chain 有效祖先 uid 列表
 * @param int $tradeType 1视频 2会员 3充值
 * @param int $scopeId 视频ID/会员组ID
 * @return array rule_id/rule_version/levels
 */
function dist_calc_rates($chain, $tradeType = 5, $scopeId = 0)
{
    global $dsql;
    $tradeType = intval($tradeType);
    $scopeId = intval($scopeId);
    $base = array(
        dist_bp(dist_cfg('rate1', 0.10)),
        dist_bp(dist_cfg('rate2', 0.05)),
        dist_bp(dist_cfg('rate3', 0.03)),
    );
    $enable = array(
        intval(dist_cfg('level1', 1)) === 1,
        intval(dist_cfg('level2', 1)) === 1,
        intval(dist_cfg('level3', 1)) === 1,
    );
    $ruleId = 0;
    $ruleVersion = 0;
    $rule = array();
    if ($tradeType === 1) {
        $rule = dist_rule_current(3, $scopeId);
    } elseif ($tradeType === 2) {
        $rule = dist_rule_current(4, $scopeId);
    } elseif ($tradeType === 3 || $tradeType === 4) {
        $rule = dist_rule_current(5, $scopeId);
    }
    if (empty($rule)) {
        $rule = dist_rule_current(1, 0);
    }
    if (!empty($rule)) {
        $ruleId = intval($rule['id']);
        $ruleVersion = intval($rule['version']);
        $base = array(
            dist_bp($rule['rate1']),
            dist_bp($rule['rate2']),
            dist_bp($rule['rate3']),
        );
    }
    $cap = array(
        dist_bp(dist_cfg('max_rate1', 0.15)),
        dist_bp(dist_cfg('max_rate2', 0.10)),
        dist_bp(dist_cfg('max_rate3', 0.08)),
    );
    $maxTotal = dist_bp(dist_cfg('max_total', 0.20));

    $levels = array();
    foreach ($chain as $idx => $beneficiary) {
        if ($idx > 2) {
            break;
        }
        if (!$enable[$idx]) {
            continue;
        }
        $gradeRow = $dsql->GetOne("SELECT grade FROM sea_dist_user WHERE uid=" . intval($beneficiary) . " limit 1");
        $grade = is_array($gradeRow) ? intval($gradeRow['grade']) : 1;
        $factorBp = dist_grade_factor_bp($grade);
        $eff = intdiv($base[$idx] * $factorBp, 10000);
        if ($eff > $cap[$idx]) {
            $eff = $cap[$idx];
        }
        if ($eff < 0) {
            $eff = 0;
        }
        $levels[] = array(
            'uid' => intval($beneficiary),
            'level' => $idx + 1,
            'rate_bp' => $eff,
            'grade' => $grade
        );
    }
    // 总额截断：从 L3 开始削减
    $total = 0;
    foreach ($levels as $lv) {
        $total += $lv['rate_bp'];
    }
    if ($total > $maxTotal) {
        $excess = $total - $maxTotal;
        for ($i = count($levels) - 1; $i >= 0 && $excess > 0; $i--) {
            $cut = min($levels[$i]['rate_bp'], $excess);
            $levels[$i]['rate_bp'] -= $cut;
            $excess -= $cut;
        }
    }
    $out = array();
    foreach ($levels as $lv) {
        if ($lv['rate_bp'] > 0) {
            $out[] = $lv;
        }
    }
    return array('rule_id' => $ruleId, 'rule_version' => $ruleVersion, 'levels' => $out);
}
