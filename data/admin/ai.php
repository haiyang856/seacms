<?php
/**
 * AI 功能配置模板（已脱敏）
 *
 * 请在后台「AI 智能扩展」页面填写真实配置后保存。
 * 请勿将真实 API 密钥、数据库凭证或内部业务标识提交到代码仓库。
 */

$ai_open = "off";                  // AI 总开关：on / off
$ai_provider = "deepseek";         // 服务商
$ai_api_url = "";                  // API 地址（按服务商填写）
$ai_api_key = "";                  // API 密钥（请勿提交真实值）
$ai_model = "deepseek-chat";       // 模型名称
$ai_temperature = "0.7";           // 生成温度
$ai_max_tokens = "800";            // 最大输出长度
$ai_search_correct = "off";        // AI 搜索纠错：on / off
$ai_recommend = "off";             // AI 影片推荐：on / off
?>
