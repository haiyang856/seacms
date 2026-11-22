-- 三级分销数据表（v1.2）
-- 存量库升级：若 sea_paylog 缺少 parent_uid/dist_level/dist_rate，请执行：
-- ALTER TABLE `sea_paylog` ADD COLUMN `parent_uid` int unsigned NOT NULL DEFAULT 0 COMMENT '上级会员ID（三级分销）' AFTER `card_key`, ADD COLUMN `dist_level` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '分销层级1/2/3' AFTER `parent_uid`, ADD COLUMN `dist_rate` decimal(6,4) NOT NULL DEFAULT 0.0000 COMMENT '分销比例' AFTER `dist_level`, ADD INDEX `idx_parent` (`parent_uid`,`dist_level`);

CREATE TABLE IF NOT EXISTS `sea_dist_user` (
  `uid` int unsigned NOT NULL COMMENT '会员ID',
  `invite_code` varchar(16) DEFAULT NULL COMMENT '邀请码',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '0待审核 1正常 2冻结 3已拒绝 4清退 5注销',
  `grade` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '1普通 2铜牌 3银牌 4金牌',
  `apply_time` int unsigned NOT NULL DEFAULT 0,
  `audit_time` int unsigned NOT NULL DEFAULT 0,
  `audit_admin` varchar(32) NOT NULL DEFAULT '',
  `audit_remark` varchar(255) NOT NULL DEFAULT '',
  `last_active_time` int unsigned NOT NULL DEFAULT 0,
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `uk_invite_code` (`invite_code`),
  KEY `idx_status` (`status`),
  KEY `idx_grade` (`grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='分销员身份表';

CREATE TABLE IF NOT EXISTS `sea_dist_relation` (
  `uid` int unsigned NOT NULL COMMENT '会员ID',
  `parent_uid` int unsigned NOT NULL DEFAULT 0,
  `p1_uid` int unsigned NOT NULL DEFAULT 0,
  `p2_uid` int unsigned NOT NULL DEFAULT 0,
  `p3_uid` int unsigned NOT NULL DEFAULT 0,
  `depth` smallint unsigned NOT NULL DEFAULT 0,
  `path` varchar(255) NOT NULL DEFAULT '',
  `bind_type` tinyint unsigned NOT NULL DEFAULT 0,
  `bind_source` varchar(20) NOT NULL DEFAULT '',
  `bind_ref` varchar(64) NOT NULL DEFAULT '',
  `status` tinyint unsigned NOT NULL DEFAULT 1,
  `bind_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  `close_reason` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`uid`),
  KEY `idx_parent` (`parent_uid`),
  KEY `idx_p1` (`p1_uid`),
  KEY `idx_p2` (`p2_uid`),
  KEY `idx_p3` (`p3_uid`),
  KEY `idx_path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='分销关系表';

CREATE TABLE IF NOT EXISTS `sea_dist_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `orderno` varchar(64) NOT NULL,
  `buyer_uid` int unsigned NOT NULL DEFAULT 0,
  `trade_type` tinyint unsigned NOT NULL DEFAULT 5,
  `base_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'CNY',
  `chain_snapshot` json DEFAULT NULL,
  `rule_id` int unsigned NOT NULL DEFAULT 0,
  `rule_version` int unsigned NOT NULL DEFAULT 0,
  `status` tinyint unsigned NOT NULL DEFAULT 0,
  `refund_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pay_time` int unsigned NOT NULL DEFAULT 0,
  `settle_time` int unsigned NOT NULL DEFAULT 0,
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orderno` (`orderno`),
  KEY `idx_buyer` (`buyer_uid`),
  KEY `idx_status_settle` (`status`,`settle_time`),
  KEY `idx_paytime` (`pay_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='分销订单快照表';

CREATE TABLE IF NOT EXISTS `sea_dist_commission` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(64) NOT NULL,
  `buyer_uid` int unsigned NOT NULL DEFAULT 0,
  `uid` int unsigned NOT NULL DEFAULT 0,
  `level` tinyint unsigned NOT NULL DEFAULT 1,
  `base_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rate` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` tinyint unsigned NOT NULL DEFAULT 0,
  `settle_time` int unsigned NOT NULL DEFAULT 0,
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  `remark` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_uid` (`order_no`,`uid`),
  KEY `idx_uid_status` (`uid`,`status`,`create_time`),
  KEY `idx_status_settle` (`status`,`settle_time`),
  KEY `idx_buyer` (`buyer_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='佣金流水表';

CREATE TABLE IF NOT EXISTS `sea_dist_account` (
  `uid` int unsigned NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pending_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `available_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `frozen_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawn_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `negative_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `points_converted` decimal(12,2) NOT NULL DEFAULT 0.00,
  `version` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='分销账户表';

CREATE TABLE IF NOT EXISTS `sea_dist_withdraw` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `withdraw_no` varchar(32) NOT NULL,
  `uid` int unsigned NOT NULL DEFAULT 0,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `real_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `channel` varchar(20) NOT NULL DEFAULT 'manual',
  `account_info` varchar(512) NOT NULL DEFAULT '',
  `status` tinyint unsigned NOT NULL DEFAULT 0,
  `audit_admin` varchar(32) NOT NULL DEFAULT '',
  `audit_time` int unsigned NOT NULL DEFAULT 0,
  `audit_remark` varchar(255) NOT NULL DEFAULT '',
  `pay_admin` varchar(32) NOT NULL DEFAULT '',
  `pay_time` int unsigned NOT NULL DEFAULT 0,
  `pay_trade_no` varchar(80) NOT NULL DEFAULT '',
  `voucher` varchar(255) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_withdraw_no` (`withdraw_no`),
  KEY `idx_uid_status` (`uid`,`status`),
  KEY `idx_status_time` (`status`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='分销提现申请表';

CREATE TABLE IF NOT EXISTS `sea_dist_rule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` tinyint unsigned NOT NULL DEFAULT 1,
  `scope_id` int unsigned NOT NULL DEFAULT 0,
  `rate1` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `rate2` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `rate3` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `grade_factor` decimal(6,4) NOT NULL DEFAULT 1.0000,
  `status` tinyint unsigned NOT NULL DEFAULT 0,
  `version` int unsigned NOT NULL DEFAULT 1,
  `effective_time` int unsigned NOT NULL DEFAULT 0,
  `expire_time` int unsigned NOT NULL DEFAULT 0,
  `admin_id` int unsigned NOT NULL DEFAULT 0,
  `create_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_scope` (`scope_type`,`scope_id`,`status`),
  KEY `idx_effective` (`effective_time`,`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='分佣规则版本表';

CREATE TABLE IF NOT EXISTS `sea_dist_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT 0,
  `action` varchar(32) NOT NULL DEFAULT '',
  `target_type` varchar(20) NOT NULL DEFAULT '',
  `target_id` varchar(64) NOT NULL DEFAULT '',
  `before_data` json DEFAULT NULL,
  `after_data` json DEFAULT NULL,
  `operator_type` tinyint unsigned NOT NULL DEFAULT 3,
  `operator_id` int unsigned NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `request_id` varchar(64) NOT NULL DEFAULT '',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_uid_time` (`uid`,`create_time`),
  KEY `idx_action_time` (`action`,`create_time`),
  KEY `idx_target` (`target_type`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='分销审计日志表';

CREATE TABLE IF NOT EXISTS `sea_dist_daily` (
  `stat_date` date NOT NULL,
  `uid` int unsigned NOT NULL DEFAULT 0,
  `team_l1` int unsigned NOT NULL DEFAULT 0,
  `team_l2` int unsigned NOT NULL DEFAULT 0,
  `team_l3` int unsigned NOT NULL DEFAULT 0,
  `order_count` int unsigned NOT NULL DEFAULT 0,
  `gmv` decimal(14,2) NOT NULL DEFAULT 0.00,
  `commission_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `refund_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `active_users` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`,`uid`),
  KEY `idx_uid_date` (`uid`,`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='分销日统计汇总表';

CREATE TABLE IF NOT EXISTS `sea_dist_promo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT 0,
  `promo_code` varchar(32) NOT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'other',
  `material_id` int unsigned NOT NULL DEFAULT 0,
  `pv` int unsigned NOT NULL DEFAULT 0,
  `uv` int unsigned NOT NULL DEFAULT 0,
  `reg_count` int unsigned NOT NULL DEFAULT 0,
  `first_order_count` int unsigned NOT NULL DEFAULT 0,
  `pay_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_promo_code` (`promo_code`),
  KEY `idx_uid_time` (`uid`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='推广渠道统计表';
