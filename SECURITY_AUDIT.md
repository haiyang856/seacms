# SeaCMS PHP 8.6 升级与安全审计报告

日期:2026-11-17 · 环境:ServBay / PHP 8.6.0RC7

## 一、PHP 8.6 升级(已完成)

| 修复项 | 位置 | 说明 |
|---|---|---|
| mysql_* 函数(13 种/27+ 处) | admin/ebak/ | 新增 `admin/ebak/class/mysql8_compat.php` 兼容层,用 mysqli 实现 mysql_* 语义(含默认连接),在 ebak 入口 connect.php 挂载 |
| mysql_* 直改 mysqli | include/updatesql.class.php | 3 处,顺带修复未定义函数 `my_select_db` 笔误 |
| `$GLOBALS` 整体赋值(PHP 8.1+ 禁止) | search/so/s/tag/ajax.php | 重复的 magic_quotes 过滤块抽取为公共文件 `include/gpc.filter.php`,移除 `$GLOBALS`、`$HTTP_RAW_POST_DATA` 等非法操作 |
| each()(23 处) | 类库+入口文件 | 全部改为 foreach 等价写法 |
| 花括号字符串偏移 `$str{0}`(约 50 处) | common.file.func.php、common.redis.func.php、JSON.php、file_manager_json.php 等 | 全部转 `$str[0]`(脚本+人工校对) |
| get_magic_quotes_gpc / set_magic_quotes_runtime | install.inc.php、common.php、sql.class.php、phpmailer、pop3、ebak | 移除,保留原 addslashes 数据流行为 |
| ereg/split | admin_makehtml2.php、admin_reslib2.php | 改 explode/preg 封装(m_eregi 本身已是 preg 封装) |
| PHP_VERSION < 4.1/4.2/5.x/6 分支 | common.php、install/index.php、ebak/functions.php、phpmailer | 全部删除,不再支持 PHP 5/7 |
| install/common.inc.php 模板 | `~dbport~` 缺引号导致 Parse error | 加引号(不影响安装替换逻辑) |

验证:214 个 PHP 文件在 PHP 8.6 下 `php -l` 全部通过;首页/安装向导/搜索/tag/留言/会员/API 等页面冒烟测试全部 HTTP 200。系统尚未安装(无 data/common.inc.php),完整运行需先执行安装向导。

## 二、代码排版(已完成)

- 对 include/ 核心类库与根目录入口共 44 个纯 PHP 文件做了零行为变更的规范化:行尾空白、多余空行、运算符间距、EOF 空行等。
- 13 个 PHP/HTML 混合模板文件与 GBK 编码文件(如 ebak)保守跳过,避免引入风险。
- 重复 70+ 行的 GPC 过滤块在 5 个入口文件中已去重为公共文件。

## 三、安全审计结果

### 已修复(本次)
1. **上传扩展名黑名单扩充** — `include/uploadsafe.inc.php`:补充 php4/5/7/8、phtml、pht、phar、htaccess 等危险扩展名(原黑名单可被 php5/phtml 绕过)。
2. **data 目录禁止 Web 访问** — 新增 `data/.htaccess`(全目录 Deny)。data/common.inc.php 含数据库凭据,原可直接 HTTP 读取。
3. **根目录敏感文件防护** — 新增 `.htaccess` 阻止 .sql/.bak/.inc/.lock/.log 等文件被下载。(注:根目录 htaccess 的授权类修改曾触发 Host 安全防护拦截,当前文件内容已保留 FilesMatch 防护;如需更强控制请自行确认。)

### 高风险(建议尽快处理)
1. **SQL 注入依赖全局 addslashes** — member.php(78KB,30+ 处拼 SQL)、gbook.php、admin 多处均直接拼接用户输入。当前靠 include/common.php 的全局 addslashes + webscan WAF 拦截,但 LIKE 通配符(%/_)可注入做全表扫描,且数字型参数未强制 intval。**建议:逐步改造为 mysqli 预处理语句($dsql->realescape 已存在可用)。**
2. **密码弱哈希** — 管理员/会员密码均为 `substr(md5(pwd),5,20)`,可被彩虹表/爆破。**建议:升级 password_hash(),登录时兼容校验旧哈希并自动重哈希。**
3. **后台无 CSRF 防护** — admin/ 全部操作无 token 校验,配合 XSS 可被 CSRF。**建议:config.php 里生成 session token,POST 操作统一校验。**

### 中风险
4. **session 安全码可预测** — login.php/admin/config.php 的 `hashstr = md5(dbpwd.dbname.dbuser)`,若泄露数据库配置即伪造。建议改为 random_bytes 随机值存 session。
5. **会员注册/找回密码逻辑** — member.php 中 repswcode、acode 直接拼 SQL,建议 intval/白名单。
6. **zyapi.php 公开资源接口** — 受 isapi.txt 开关控制,但未加访问密钥,可被第三方抓取全站数据。建议加 API key。
7. **安装向导锁依赖手工删除** — install_lock.txt 机制存在,但建议安装完成后直接删除 install/ 目录。
8. **弹幕后台遗留空密码字段** — `js/player/dmplayer/admin/config.php` 的 adminpass 为空(实际鉴权走主 CMS session,字段为死配置),建议删除。

### 低风险
9. 错误输出:多数入口 error_reporting(0),生产环境正常;建议关闭 display_errors 并开启日志。
10. uploads/ 目录建议禁 PHP 执行(nginx: `location ~* ^/uploads/.*\.php$ { deny all; }`)。

### 防线现状(正面)
- webscan WAF(GET/POST/COOKIE 正则拦截)仍在工作;
- include/common.php 有 GPC 键名注入保护(cfg_/GLOBALS 等)并全局过滤;
- 后台鉴权链完整(admin/* → config.php → userLogin session);
- 上传有扩展名黑名单 + 图片内容检测(getimagesize)。

## 四、后续建议

1. 执行安装向导完成系统安装后,做一次完整功能回归(搜索、播放、会员、后台备份)。
2. ebak(帝国备份王)与 phpmailer/Snoopy 建议长期替换为现代组件(现为兼容层维持)。
3. 数据库层逐步参数化,优先 member.php 与 admin 写操作。
