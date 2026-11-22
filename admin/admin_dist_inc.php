<?php
/**
 * 三级分销后台公共文件
 * 仅由 zzz/admin_dist_*.php 引入
 */
require_once(dirname(__FILE__) . '/config.php');
if (!defined('sea_ADMIN')) {
    exit('Request Error!');
}
require_once(sea_ROOT . '/include/dist/bootstrap.php');

function dist_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dist_admin_operator()
{
    return isset($_SESSION['sea_admin_name']) ? (string)$_SESSION['sea_admin_name'] : '';
}

/**
 * 后台 CSRF token
 */
function dist_csrf()
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(16));
    }
    return (string)$_SESSION['admin_csrf_token'];
}

function dist_admin_head($title, $active = '')
{
    $nav = array(
        'admin_dist.php' => '数据看板',
        'admin_dist_user.php' => '分销员',
        'admin_dist_commission.php' => '佣金流水',
        'admin_dist_withdraw.php' => '提现审核',
        'admin_dist_rule.php' => '规则配置',
        'admin_dist_log.php' => '审计日志'
    );
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow">';
    echo '<title>' . dist_h($title) . '</title>';
    echo '<link href="img/style.css" rel="stylesheet" type="text/css" />';
    echo '<script src="js/common.js" type="text/javascript"></script><script src="js/main.js" type="text/javascript"></script>';
    echo '<style>
    body{background:#f5f6f8;}
    .dist-wrap{padding:16px 18px 30px;font-size:13px;color:#303133;}
    .dist-page-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px;}
    .dist-page-head h1{font-size:20px;margin:0;font-weight:600;}
    .dist-nav{display:flex;flex-wrap:wrap;gap:6px;}
    .dist-nav a{padding:6px 12px;border-radius:15px;background:#fff;border:1px solid #e4e7ed;color:#333;text-decoration:none;font-size:12px;}
    .dist-nav a.active{background:#e8f1ff;border-color:#b9d4fb;color:#1667d1;font-weight:600;}
    .dist-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:12px 0;}
    .dist-card{background:#fff;border:1px solid #e6ebf0;border-radius:6px;padding:14px 16px;box-shadow:0 1px 2px rgba(0,0,0,.03);}
    .dist-card .label{color:#6b7280;font-size:12px;margin-bottom:6px;}
    .dist-card .value{font-size:22px;font-weight:600;color:#1f2937;line-height:1.2;}
    .dist-card .sub{color:#98a2b3;font-size:12px;margin-top:6px;}
    .dist-card.blue{border-left:4px solid #2563eb;}
    .dist-card.green{border-left:4px solid #10b981;}
    .dist-card.orange{border-left:4px solid #f59e0b;}
    .dist-card.red{border-left:4px solid #ef4444;}
    .dist-panel{background:#fff;border:1px solid #e6ebf0;border-radius:6px;padding:14px 16px;margin-top:14px;}
    .dist-panel h2{font-size:15px;margin:0 0 10px;font-weight:600;}
    .dist-filter{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:10px 0;}
    .dist-filter input[type=text],.dist-filter select{padding:6px 9px;border:1px solid #dcdfe6;border-radius:4px;height:30px;box-sizing:border-box;font-size:12px;}
    .dist-table{width:100%;border-collapse:collapse;}
    .dist-table th,.dist-table td{border-bottom:1px solid #f0f2f5;padding:8px 8px;text-align:left;vertical-align:middle;}
    .dist-table th{background:#fafbfc;color:#606266;font-weight:600;font-size:12px;white-space:nowrap;}
    .dist-table td{font-size:12px;}
    .dist-table tr:hover td{background:#fafcff;}
    .dist-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;line-height:18px;background:#f0f2f5;color:#555;}
    .dist-b0{background:#fff7e6;color:#ad6800;}
    .dist-b1{background:#e6f7eb;color:#0a7d32;}
    .dist-b2{background:#e8f1ff;color:#1667d1;}
    .dist-b3{background:#f0f2f5;color:#111;}
    .dist-b4,.dist-b5{background:#fff1f0;color:#cf1322;}
    .dist-b6{background:#f0f2f5;color:#666;}
    .dist-btn{display:inline-block;padding:4px 10px;border-radius:4px;border:1px solid #dcdfe6;background:#fff;color:#333;text-decoration:none;font-size:12px;cursor:pointer;}
    .dist-btn:hover{border-color:#1667d1;color:#1667d1;}
    .dist-btn.primary{background:#1667d1;border-color:#1667d1;color:#fff;}
    .dist-btn.danger{color:#cf1322;border-color:#f5c6c2;}
    .dist-btn.warn{color:#ad6800;border-color:#ffe0a3;}
    .dist-pager{margin:12px 0 0;display:flex;gap:10px;align-items:center;color:#666;}
    .dist-pager a{padding:4px 9px;border:1px solid #dcdfe6;border-radius:4px;color:#333;text-decoration:none;}
    .dist-form-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;}
    .dist-form-row label{display:block;color:#606266;font-size:12px;margin-bottom:4px;}
    .dist-form-row input[type=text],.dist-form-row select{width:100%;padding:6px 9px;border:1px solid #dcdfe6;border-radius:4px;box-sizing:border-box;height:32px;}
    .dist-help{color:#98a2b3;font-size:12px;margin:6px 0;}
    .dist-empty{text-align:center;color:#98a2b3;padding:18px;}
    .dist-inline{display:inline-block;margin-right:4px;vertical-align:middle;}
    .dist-inline form{display:inline;}
    .dist-tip{background:#f0f7ff;border:1px solid #d6e8ff;color:#1667d1;padding:8px 12px;border-radius:4px;margin:10px 0;}
    @media (max-width:1100px){.dist-cards{grid-template-columns:repeat(2,minmax(0,1fr));}.dist-form-row{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media (max-width:700px){.dist-cards,.dist-form-row{grid-template-columns:1fr;}}
    </style></head><body><div class="dist-wrap">';
    echo '<div class="dist-page-head"><h1>' . dist_h($title) . '</h1><div class="dist-nav">';
    foreach ($nav as $href => $label) {
        $cls = $href === $active ? ' active' : '';
        echo '<a href="' . $href . '" class="' . trim($cls) . '">' . dist_h($label) . '</a>';
    }
    echo '</div></div>';
}

function dist_admin_foot()
{
    echo '</div>';
    if (function_exists('viewFoot')) {
        viewFoot();
    }
    echo '</body></html>';
}

/**
 * 通用分页 HTML
 */
function dist_admin_pager($total, $page, $perPage, $baseUrl)
{
    $total = intval($total);
    $perPage = max(1, intval($perPage));
    $totalPage = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, intval($page)), $totalPage);
    $html = '<div class="dist-pager"><span>共 ' . $total . ' 条 / ' . $totalPage . ' 页</span>';
    $html .= '<a href="' . dist_h($baseUrl . 'page=1') . '">首页</a>';
    $html .= '<a href="' . dist_h($baseUrl . 'page=' . max(1, $page - 1)) . '">上一页</a>';
    $html .= '<a href="' . dist_h($baseUrl . 'page=' . min($totalPage, $page + 1)) . '">下一页</a>';
    $html .= '<a href="' . dist_h($baseUrl . 'page=' . $totalPage) . '">尾页</a>';
    $html .= '</div>';
    return $html;
}

function dist_admin_page_param($key, $default = 0)
{
    return isset($_GET[$key]) ? intval($_GET[$key]) : intval($default);
}

/**
 * 输出 CSV 导出文件（Excel 可直接打开）
 * 必须在 dist_admin_head() 之前调用，输出后自动 exit
 * @param string $filename 文件名（不含扩展名）
 * @param array $headers 表头
 * @param array $rows 数据行，每行顺序与表头一致
 */
function dist_admin_export_csv($filename, $headers, $rows)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $filename = preg_replace('/[^0-9A-Za-z_\x{4e00}-\x{9fa5}\-]/u', '', (string)$filename);
    if ($filename === '') {
        $filename = 'export';
    }
    $filename .= '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
