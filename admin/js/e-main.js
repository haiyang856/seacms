/**
 * Admin Main JS
 * 后台管理主要功能脚本
 */

// ========== 初始化菜单 ==========
function initMenu(menuId) {
  var menuon = getCookie('MENUON');
  var dc = $(menuId);
  if (dc && menuon) {
    dc.style.display = 'none';
  }
  return null;
}

// ========== 获取菜单名称 ==========
function getMenu(key) {
  var el = $('header_' + key);
  return el ? el.textContent : '';
}

// ========== 切换菜单 ==========
function changeMenu(key, url) {
  if (key == 'index' && url == 'index.php') {
    parent.location.href = 'index.php';
    return false;
  }

  // 切换子菜单显示
  if (typeof topMenus !== 'undefined') {
    for (var k in topMenus) {
      var menuEl = $('menu_' + topMenus[k]);
      if (menuEl) {
        menuEl.style.display = topMenus[k] == key ? '' : 'none';
      }
    }
  }

  // 更新顶部导航激活状态
  var topItems = document.querySelectorAll('.el-topnav-item');
  topItems.forEach(function(item) {
    item.classList.remove('active');
  });

  var headerEl = $('header_' + key);
  if (headerEl) headerEl.classList.add('active');

  // 加载内容
  if (url) {
    parent.I2.location = url;

    // 更新子菜单激活状态
    var menuContainer = $('menu_' + key);
    if (menuContainer) {
      var hrefs = menuContainer.getElementsByTagName('a');
      for (var j = 0; j < hrefs.length; j++) {
        hrefs[j].className = hrefs[j].href.indexOf(url) != -1 ? 'menucurr' : (hrefs[j].className == 'menucurr' ? '' : hrefs[j].className);
      }
    }
  }

  // 更新面包屑
  if (parent.$('admincpnav')) {
    parent.$('admincpnav').innerHTML = '后台首页&nbsp;&raquo;&nbsp;' + getMenu(key);
  }

  return false;
}

// ========== 更新侧边栏高度 ==========
function updateSidebarHeight() {
  var h = document.body.clientHeight - 60;
  var leftmenu = $("leftmenu");
  if (leftmenu) {
    leftmenu.style.minHeight = h + "px";
  }
}

// ========== 折叠/展开菜单 ==========
function tooglemenutd() {
  var dc = $('menutd');
  if (!dc) return;

  if (dc.style.display != 'none') {
    dc.style.width = '0';
    dc.style.display = 'none';
    setCookie('MENUON', '1', 365 * 24);
  } else {
    dc.style.width = '200px';
    dc.style.display = '';
    setCookie('MENUON', '', 365 * 24);
  }
}

// ========== 刷新iframe ==========
function refreshFrame() {
  var iframe = document.getElementById('I2');
  if (iframe) {
    iframe.contentWindow.location.reload(true);
  }
}

// ========== 页面加载初始化 ==========
myReady(function() {
  // 初始化侧边栏高度
  updateSidebarHeight();

  // 窗口大小变化时重新计算
  var resizeTimer = null;
  window.onresize = function() {
    if (resizeTimer == null) {
      resizeTimer = setTimeout(function() {
        updateSidebarHeight();
        resizeTimer = null;
      }, 300);
    }
  };

  // 添加菜单hover效果
  var menuItems = document.querySelectorAll('.el-menu-item');
  menuItems.forEach(function(item) {
    item.addEventListener('click', function(e) {
      // 移除其他active
      menuItems.forEach(function(i) { i.classList.remove('active'); });
      // 添加当前active
      this.classList.add('active');
    });
  });

  // 添加平滑滚动
  var aside = document.querySelector('.el-aside');
  if (aside) {
    aside.style.scrollBehavior = 'smooth';
  }
});
