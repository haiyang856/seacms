/**
 * ElementUI Admin Common JS
 * 海洋CMS后台管理通用脚本
 */

// ========== 工具函数 ==========
function $(id) {
  return document.getElementById(id);
}

function $$(selector) {
  return document.querySelectorAll(selector);
}

function set(el, content) {
  if (el) {
    el.innerHTML = content;
  }
}

function view(id) {
  var el = $(id);
  if (el) el.style.display = '';
}

function hide(id) {
  var el = $(id);
  if (el) el.style.display = 'none';
}

function toggle(id) {
  var el = $(id);
  if (el) {
    el.style.display = el.style.display === 'none' ? '' : 'none';
  }
}

// ========== Cookie 操作 ==========
function setCookie(name, value, hours) {
  var expires = '';
  if (hours) {
    var date = new Date();
    date.setTime(date.getTime() + (hours * 3600 * 1000));
    expires = '; expires=' + date.toUTCString();
  }
  document.cookie = name + '=' + value + expires + '; path=/';
}

function getCookie(name) {
  var nameEQ = name + '=';
  var ca = document.cookie.split(';');
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) === ' ') c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
  }
  return null;
}

// ========== Tabs 功能 ==========
function initTabs(containerId) {
  var container = $(containerId);
  if (!container) return;

  var tabs = container.querySelectorAll('.el-tabs__item');
  var contents = container.querySelectorAll('.el-tabs__content > div');

  tabs.forEach(function(tab, index) {
    tab.addEventListener('click', function() {
      // 移除所有active
      tabs.forEach(function(t) { t.classList.remove('active'); });
      contents.forEach(function(c) { c.style.display = 'none'; });

      // 激活当前
      this.classList.add('active');
      if (contents[index]) {
        contents[index].style.display = 'block';
        contents[index].style.animation = 'fadeIn 0.3s ease-out';
      }
    });
  });
}

// ========== 确认对话框 ==========
function confirmAction(message, callback) {
  if (confirm(message || '确定要执行此操作吗？')) {
    if (typeof callback === 'function') {
      callback();
    }
    return true;
  }
  return false;
}

// ========== 批量操作 ==========
function getCheckedValues(name) {
  var checkboxes = document.querySelectorAll('input[name="' + name + '"]:checked');
  var values = [];
  checkboxes.forEach(function(cb) {
    values.push(cb.value);
  });
  return values;
}

function checkAll(name, checked) {
  var checkboxes = document.querySelectorAll('input[name="' + name + '"]');
  checkboxes.forEach(function(cb) {
    cb.checked = checked;
  });
}

function toggleCheckAll(source, name) {
  var checkboxes = document.querySelectorAll('input[name="' + name + '"]');
  checkboxes.forEach(function(cb) {
    cb.checked = source.checked;
  });
}

// ========== 表单验证 ==========
function validateForm(rules) {
  for (var i = 0; i < rules.length; i++) {
    var rule = rules[i];
    var el = $(rule.id);
    if (!el) continue;

    var value = el.value.trim();

    if (rule.required && !value) {
      alert(rule.message || '请填写必填项');
      el.focus();
      return false;
    }

    if (rule.min && value.length < rule.min) {
      alert(rule.message || '长度不能少于' + rule.min + '个字符');
      el.focus();
      return false;
    }

    if (rule.pattern && !rule.pattern.test(value)) {
      alert(rule.message || '输入格式不正确');
      el.focus();
      return false;
    }
  }
  return true;
}

// ========== AJAX 封装 (使用原始系统的 ajax 对象，避免冲突) ==========
// 注意：不定义 ajax 变量，使用 ../../js/common.js 中定义的 AJAX 对象

// ========== 页面加载完成执行 ==========
function myReady(fn) {
  if (document.readyState !== 'loading') {
    fn();
  } else {
    document.addEventListener('DOMContentLoaded', fn);
  }
}



// ========== 全屏切换 ==========
var fullss = 0;

function fullscreen() {
  var docElm = document.documentElement;
  if (docElm.requestFullscreen) { docElm.requestFullscreen(); }
  else if (docElm.mozRequestFullScreen) { docElm.mozRequestFullScreen(); }
  else if (docElm.webkitRequestFullScreen) { docElm.webkitRequestFullScreen(); }
  else if (docElm.msRequestFullscreen) { docElm.msRequestFullscreen(); }
  return fullss = 1;
}

function exitFullscreen() {
  if (document.exitFullscreen) { document.exitFullscreen(); }
  else if (document.mozCancelFullScreen) { document.mozCancelFullScreen(); }
  else if (document.webkitCancelFullScreen) { document.webkitCancelFullScreen(); }
  else if (document.msExitFullscreen) { document.msExitFullscreen(); }
  return fullss = 0;
}

function fullsw(fulls) {
  if (fulls == 1) { exitFullscreen(); } else { fullscreen(); }
}

// ========== 初始化菜单 ==========
function initMenu(menuId) {
  var menuon = getCookie('MENUON');
  var dc = $(menuId);
  if (dc) {
    if (menuon) {
      dc.style.display = 'none';
    }
  }
  return null;
}

// ========== 获取滚动位置 ==========
function getScroll() {
  return document.documentElement.scrollTop || document.body.scrollTop;
}

// ========== 展开播放区域 ==========
function expendPlayArea(num, playerStr, show) {
  var area = $('v_playarea');
  if (!area) return;

  var div = document.createElement('div');
  div.className = 'source-card';
  div.id = 'playfb' + num;
  div.style.animation = 'fadeInUp 0.2s ease-out';

  var header = document.createElement('div');
  header.className = 'source-card__header';

  var label = document.createElement('div');
  label.className = 'source-label';
  label.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>播放源 ' + num;

  var sel = document.createElement('select');
  sel.id = 'v_playfrom' + num;
  sel.name = 'v_playfrom[' + num + ']';
  sel.className = 'el-select';
  sel.style.minWidth = '160px';
  sel.innerHTML = '<option value="">暂无数据' + num + '</option>' + decodeURIComponent(playerStr);

  var delBtn = document.createElement('button');
  delBtn.type = 'button';
  delBtn.className = 'el-button el-button--small el-button--danger';
  delBtn.title = '删除播放来源' + num;
  delBtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>删除';
  delBtn.onclick = function() {
    var tb = document.getElementById('playfb' + num);
    if (tb) tb.parentNode.removeChild(tb);
  };

  header.appendChild(label);
  header.appendChild(sel);
  header.appendChild(delBtn);

  var body = document.createElement('div');
  body.className = 'source-card__body';

  var labelInline = document.createElement('div');
  labelInline.className = 'source-label-inline';
  labelInline.innerHTML = '地址 ' + num + '：<br/>';

  var repairBtn = document.createElement('input');
  repairBtn.type = 'button';
  repairBtn.value = '手动校正';
  repairBtn.title = '一般情况下不需要手动校正，系统会自动进行校正';
  repairBtn.className = 'el-button el-button--small el-button--clear';
  repairBtn.style.marginTop = '6px';
  repairBtn.onclick = function() {
    repairUrl(num);
  };

  labelInline.appendChild(repairBtn);

  var textarea = document.createElement('textarea');
  textarea.id = 'v_playurl' + num;
  textarea.name = 'v_playurl[' + num + ']';
  textarea.rows = 8;

  body.appendChild(labelInline);
  body.appendChild(textarea);

  div.appendChild(header);
  div.appendChild(body);

  area.appendChild(div);
}

function expendDownArea(num, playerStr, show) {
  var area = $('m_downarea');
  if (!area) return;

  var div = document.createElement('div');
  div.className = 'source-card';
  div.id = 'downfb' + num;
  div.style.animation = 'fadeInUp 0.2s ease-out';

  var header = document.createElement('div');
  header.className = 'source-card__header';

  var label = document.createElement('div');
  label.className = 'source-label';
  label.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>下载源 ' + num;

  var sel = document.createElement('select');
  sel.id = 'm_downfrom' + num;
  sel.name = 'm_downfrom[' + num + ']';
  sel.className = 'el-select';
  sel.style.minWidth = '160px';
  sel.innerHTML = '<option value="">暂无数据' + num + '</option>' + decodeURIComponent(playerStr);

  var delBtn = document.createElement('button');
  delBtn.type = 'button';
  delBtn.className = 'el-button el-button--small el-button--danger';
  delBtn.title = '删除下载来源' + num;
  delBtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>删除';
  delBtn.onclick = function() {
    var tb = document.getElementById('downfb' + num);
    if (tb) tb.parentNode.removeChild(tb);
  };

  header.appendChild(label);
  header.appendChild(sel);
  header.appendChild(delBtn);

  var body = document.createElement('div');
  body.className = 'source-card__body';

  var labelInline = document.createElement('div');
  labelInline.className = 'source-label-inline';
  labelInline.innerHTML = '地址 ' + num + '：<br/>';

  var repairBtn = document.createElement('input');
  repairBtn.type = 'button';
  repairBtn.value = '手动校正';
  repairBtn.title = '一般情况下不需要手动校正，系统会自动进行校正';
  repairBtn.className = 'el-button el-button--small el-button--clear';
  repairBtn.style.marginTop = '6px';
  repairBtn.onclick = function() {
    repairUrl2(num);
  };

  labelInline.appendChild(repairBtn);

  var textarea = document.createElement('textarea');
  textarea.id = 'm_downurl' + num;
  textarea.name = 'm_downurl[' + num + ']';
  textarea.rows = 8;

  body.appendChild(labelInline);
  body.appendChild(textarea);

  div.appendChild(header);
  div.appendChild(body);

  area.appendChild(div);
}

// ========== 显示状态框 ==========
function isViewState() {
  var checkbox = $('v_statebox');
  var span = $('v_statespan');
  if (checkbox && span) {
    span.style.display = checkbox.checked ? '' : 'none';
  }
}

// ========== 检查重复 ==========
function checkRepeat() {
  var name = $('v_name');
  var result = $('v_name_ok');
  if (!name || !result) return;

  ajax.get('admin_ajax.php?action=checkname&name=' + encodeURIComponent(name.value), function(response) {
    result.innerHTML = response;
  });
}

// ========== 动态行操作 ==========
function addRow(tableId) {
  var table = $(tableId);
  if (!table) return;

  var tbody = table.querySelector('tbody') || table;
  var rows = tbody.querySelectorAll('tr');
  if (rows.length === 0) return;

  var lastRow = rows[rows.length - 1];
  var newRow = lastRow.cloneNode(true);

  // 清空输入框
  var inputs = newRow.querySelectorAll('input[type="text"], textarea');
  inputs.forEach(function(input) {
    input.value = '';
  });

  tbody.appendChild(newRow);
  newRow.style.animation = 'fadeInUp 0.3s ease-out';
}

function removeRow(button) {
  var row = button.closest('tr');
  if (row) {
    row.style.animation = 'fadeOut 0.2s ease-out';
    setTimeout(function() {
      row.remove();
    }, 200);
  }
}

// ========== 图片预览 ==========
function previewImage(src) {
  if (!src) return;

  var overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:3000;display:flex;align-items:center;justify-content:center;cursor:pointer;animation:fadeIn 0.2s;';
  overlay.onclick = function() { overlay.remove(); };

  var img = document.createElement('img');
  img.src = src;
  img.style.cssText = 'max-width:90%;max-height:90%;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);';

  overlay.appendChild(img);
  document.body.appendChild(overlay);
}

// ========== 时间格式化 ==========
function formatDate(timestamp, format) {
  var date = new Date(timestamp * 1000);
  format = format || 'YYYY-MM-DD HH:mm:ss';

  var o = {
    'M+': date.getMonth() + 1,
    'D+': date.getDate(),
    'H+': date.getHours(),
    'm+': date.getMinutes(),
    's+': date.getSeconds()
  };

  if (/(Y+)/.test(format)) {
    format = format.replace(RegExp.$1, (date.getFullYear() + '').substr(4 - RegExp.$1.length));
  }

  for (var k in o) {
    if (new RegExp('(' + k + ')').test(format)) {
      format = format.replace(RegExp.$1, RegExp.$1.length === 1 ? o[k] : ('00' + o[k]).substr(('' + o[k]).length));
    }
  }

  return format;
}

// ========== 数字格式化 ==========
function formatNumber(num) {
  if (num >= 10000) {
    return (num / 10000).toFixed(1) + '万';
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'k';
  }
  return num.toString();
}

// ========== 初始化 ==========
myReady(function() {
  // 初始化tabs
  initTabs('tab');

  // 添加表格hover动画
  var tables = document.querySelectorAll('.el-table tbody tr');
  tables.forEach(function(row) {
    row.addEventListener('mouseenter', function() {
      this.style.transition = 'background 0.2s';
    });
  });
});


