<?php
// 禁用所有缓存，确保每次都是最新数据
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// 读取新闻数据（带自动备份恢复）
$jsonPath = __DIR__ . '/news_data.json';
$backupDir = __DIR__ . '/backups';
$allNews = [];
$recoveredFromBackup = false;

function getLatestNewsBackup($dir) {
    $files = glob($dir . '/news_data.*.json');
    if (!$files || count($files) === 0) return null;
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    foreach ($files as $file) {
        $raw = @file_get_contents($file);
        $data = json_decode($raw, true);
        if ($data && is_array($data) && count($data) > 0) {
            return $data;
        }
    }
    return null;
}

if (file_exists($jsonPath)) {
    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);
    if ($data && is_array($data) && count($data) > 0) {
        $allNews = $data;
    }
}

// 如果主文件为空或损坏，尝试从备份自动恢复
if (count($allNews) === 0 && is_dir($backupDir)) {
    $backup = getLatestNewsBackup($backupDir);
    if ($backup && count($backup) > 0) {
        $allNews = $backup;
        $recoveredFromBackup = true;
        // 将备份写回主文件，确保下次正常读取
        @file_put_contents($jsonPath, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }
}

// 按日期排序（最新的在前）
usort($allNews, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// 分页参数
$perPage = 8;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$totalPages = max(1, ceil(count($allNews) / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$pagedNews = array_slice($allNews, $offset, $perPage);
$featuredNotice = $allNews[0] ?? null;

// 提取第一张图片的函数
function extractFirstImg($content) {
    if (!$content) return '';
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
        return htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
    }
    return '/news-default.jpg';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>通知公告 - 学生志愿教导大队</title><meta name="description" content="学生志愿教导大队通知公告与新闻动态。">
    <meta name="author" content="学生志愿教导大队"><meta name="robots" content="index, follow"><link rel="canonical" href="https://www.jiaodao.fun/news.php"><link rel="icon" type="image/x-icon" href="/favicon.ico"><link rel="apple-touch-icon" href="/logo.jpg">
    <meta property="og:type" content="website"><meta property="og:url" content="https://www.jiaodao.fun/news.php"><meta property="og:title" content="通知公告 - 学生志愿教导大队"><meta property="og:image" content="https://www.jiaodao.fun/logo.jpg"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="通知公告 - 学生志愿教导大队">
    <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin><link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin><link rel="dns-prefetch" href="//cdn.tailwindcss.com"><link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preload" href="/logo.jpg" as="image" fetchpriority="high">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{'pkured':'#8B0000','pkured-dark':'#6B0000','deepblue':'#1E3A8A','golden':'#D4AF37','cloud':'#F8F5F0'},fontFamily:{serif:['Georgia','SimSun','serif'],sans:['Inter','system-ui','sans-serif']}}}}</script>
    <style>*{margin:0;padding:0;box-sizing:border-box}html{scroll-behavior:smooth}body{font-family:'Inter',system-ui,sans-serif;background:#fff;color:#333;line-height:1.8;overflow-x:hidden}{-webkit-overflow-scrolling:touch}.pagination-container{margin-top:24px;padding:16px 0}.page-btn{min-width:44px;min-height:44px;height:44px;padding:0 14px;display:inline-flex;align-items:center;justify-content:center;background:#fff;color:#8B0000;border:1.5px solid #e5e7eb;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;margin:2px}.page-btn:hover:not(.disabled):not(.active){background:#fef2f2;border-color:#8B0000;transform:translateY(-1px);box-shadow:0 2px 8px rgba(139,0,0,.15)}.page-btn.active{background:linear-gradient(135deg,#8B0000 0%,#a00000 100%);color:#fff;border-color:#8B0000;box-shadow:0 4px 12px rgba(139,0,0,.3)}.page-btn.disabled{background:#f3f4f6;color:#9ca3af;cursor:not-allowed}.pagination-info{font-size:14px;color:#6b7280;margin-left:16px}.pagination-info strong{color:#8B0000;font-size:16px;font-weight:700}@media(max-width:640px){.page-btn{padding:0 8px;font-size:12px;min-width:auto;min-height:32px;height:32px;border-radius:6px;margin:1px;white-space:nowrap}.pagination-container{padding:8px 0;flex-wrap:wrap}.pagination-info{font-size:12px;margin-left:8px;width:100%;text-align:center;margin-top:6px}.pagination-info strong{font-size:13px}}.card-hover{transition:all 300ms cubic-bezier(.4,0,.2,1)}.card-hover:hover{transform:translateY(-3px);box-shadow:0 20px 40px rgba(0,0,0,.15)}.fade-in{opacity:0;transform:translateY(30px);transition:all 600ms}.fade-in.visible{opacity:1;transform:translateY(0)}.nav-link{position:relative;transition:all 200ms}.nav-link::after{content:'';position:absolute;bottom:-2px;left:50%;width:0;height:2px;background:#D4AF37;transition:all 300ms;transform:translateX(-50%)}.nav-link:hover::after{width:80%}.news-item{transition:all 200ms;cursor:pointer;border-left:3px solid transparent}.news-item:hover{background:linear-gradient(to right,#fef2f2,transparent);padding-left:24px;border-left-color:#8B0000}@media(max-width:768px){.news-item{padding:16px!important;border-radius:12px!important;margin-bottom:12px}.news-item h3{font-size:15px!important;line-height:1.5!important;font-weight:700!important}.news-item p{font-size:13px!important;line-height:1.6!important;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}}@media(min-width:769px){.news-item h3{font-size:18px;line-height:1.4}.news-item p{font-size:14px;line-height:1.7}}.mobile-menu{position:fixed;top:0;right:0;width:75%;max-width:280px;height:100%;background:rgba(255,255,255,.95);backdrop-filter:blur(10px);box-shadow:-10px 0 40px rgba(0,0,0,.15);transform:translateX(100%);transition:transform 400ms;z-index:1000;overflow-y:auto}.mobile-overlay{position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:999}.mobile-menu-item{opacity:0;transform:translateX(20px);transition:.3s}#mobileMenu.menu-open .mobile-menu-item{opacity:1;transform:translateX(0)}#mobileMenu.menu-open .mobile-menu-item:nth-child(1){transition-delay:.05s}#mobileMenu.menu-open .mobile-menu-item:nth-child(2){transition-delay:.1s}#mobileMenu.menu-open .mobile-menu-item:nth-child(3){transition-delay:.15s}#mobileMenu.menu-open .mobile-menu-item:nth-child(4){transition-delay:.2s}#mobileMenu.menu-open .mobile-menu-item:nth-child(5){transition-delay:.25s}#mobileMenu.menu-open .mobile-menu-item:nth-child(6){transition-delay:.3s}#mobileMenu.menu-open .mobile-menu-item:nth-child(7){transition-delay:.35s}#mobileMenu.menu-open .mobile-menu-item:nth-child(8){transition-delay:.4s}#mobileMenu.menu-open .mobile-menu-item:nth-child(9){transition-delay:.45s}#mobileMenu.menu-open .mobile-menu-item:nth-child(10){transition-delay:.5s}.nav-link.active{color:#8B0000;font-weight:600}

/* 导航栏 - 与首页一致 */
.nav-header{position:fixed;top:0;left:0;right:0;z-index:50;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);border-bottom:1px solid rgba(0,0,0,.06);transition:all .3s ease}
.nav-header.scrolled{box-shadow:0 2px 20px rgba(0,0,0,.08)}
.nav-link{position:relative;color:#374151;font-size:14px;font-weight:500;padding:4px 0;transition:color 200ms;white-space:nowrap}
.nav-link:hover{color:#8B0000}
.nav-link::after{content:'';position:absolute;bottom:-2px;left:50%;width:0;height:2px;background:#D4AF37;transition:all 300ms;transform:translateX(-50%)}
.nav-link:hover::after{width:70%}
.dropdown{position:relative}
.dropdown-menu{position:absolute;top:100%;left:0;min-width:160px;background:#fff;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.12);opacity:0;visibility:hidden;transform:translateY(8px);transition:all 250ms;padding:8px 0;z-index:60}
.dropdown:hover .dropdown-menu{opacity:1;visibility:visible;transform:translateY(0)}
.dropdown-menu a{display:block;padding:8px 20px;font-size:13px;color:#4b5563;transition:all 150ms}
.dropdown-menu a:hover{background:#fef2f2;color:#8B0000}
.mobile-menu{position:fixed;top:0;right:0;width:75%;max-width:300px;height:100%;background:rgba(255,255,255,.98);backdrop-filter:blur(12px);box-shadow:-10px 0 40px rgba(0,0,0,.15);transform:translateX(100%);transition:transform 400ms cubic-bezier(.4,0,.2,1);z-index:70;overflow-y:auto}
.mobile-menu.menu-open{transform:translateX(0)}
.mobile-menu-item{opacity:0;transform:translateX(20px);transition:all 300ms ease}
#mobileMenu.menu-open .mobile-menu-item{opacity:1;transform:translateX(0)}

/* 页脚社交图标 - 与首页一致 */
.social-icon-wrapper{position:relative;display:inline-block}
.social-icon-wrapper .qr-popup{position:absolute;bottom:calc(100% + 12px);left:50%;transform:translateX(-50%) scale(.9);background:#fff;border-radius:10px;padding:10px;box-shadow:0 8px 30px rgba(0,0,0,.18);opacity:0;visibility:hidden;transition:all 250ms;z-index:80;min-width:120px;pointer-events:none}
.social-icon-wrapper .qr-popup::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:8px solid transparent;border-top-color:#fff}
.social-icon-wrapper:hover .qr-popup{opacity:1;visibility:visible;transform:translateX(-50%) scale(1)}

/* 通知公告新版UI */
.notice-tab{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:9px 15px;border:1px solid #fee2e2;background:#fff;color:#4b5563;font-size:13px;font-weight:700;transition:all .2s}
.notice-tab:hover,.notice-tab.active{background:#8B0000;color:#fff;border-color:#8B0000;box-shadow:0 10px 22px rgba(139,0,0,.18)}
.notice-card{position:relative;border:1px solid #f3e8e8;background:#fff;border-radius:20px;padding:22px;box-shadow:0 12px 34px rgba(15,23,42,.06);transition:all .22s ease}
.notice-card:hover{transform:translateY(-4px);box-shadow:0 18px 45px rgba(139,0,0,.12);border-color:#fecaca}
.notice-tag{display:inline-flex;align-items:center;gap:6px;border-radius:999px;background:#fef2f2;color:#8B0000;padding:4px 10px;font-size:12px;font-weight:700}
@media(max-width:768px){.notice-card{padding:18px}.notice-tab{font-size:12px;padding:8px 12px}}

/* 导航栏下方留出空间（fixed定位） */
body{padding-top:64px}
@media(min-width:768px){body{padding-top:72px}}</style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-white">
<div style="position:absolute;width:1px;height:1px;overflow:hidden;opacity:0"><img src="/logo.jpg" alt=""></div>

<!-- ==================== 导航栏 - 与首页一致 ==================== -->
<header id="mainNav" class="nav-header">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16 md:h-18">
            <!-- Logo区域 -->
            <a href="index.html" class="flex items-center gap-3 group">
                <img src="/logo.jpg" alt="学生志愿教导大队 Logo"
                     class="w-9 h-9 md:w-11 md:h-11 rounded-full object-cover shadow-sm flex-shrink-0"
                     loading="eager" fetchpriority="high" decoding="sync"
                     onerror="this.style.display='none'">
                <div>
                    <h1 class="text-lg md:text-xl font-semibold text-gray-800 font-serif tracking-wide">学生志愿教导大队</h1>
                </div>
            </a>

            <!-- PC端导航 -->
            <nav class="hidden lg:flex items-center gap-7">
                    <a href="index.html" class="nav-link">首页</a>
                    <a href="about.html" class="nav-link">大队简介</a>
                    <a href="org.html" class="nav-link">组织架构</a>
                    <a href="news.php" class="nav-link active">通知公告</a>

                    <div class="relative dropdown">
                        <a class="nav-link">人员信息 <i class="fas fa-chevron-down text-xs ml-0.5 opacity-60"></i></a>
                        <div class="dropdown-menu">
                            <a href="current.html">第十五届干部</a>
                            <a href="history.html">历任干部</a>
                        </div>
                    </div>

                    <div class="relative dropdown">
                        <a class="nav-link">荣誉墙 <i class="fas fa-chevron-down text-xs ml-0.5 opacity-60"></i></a>
                        <div class="dropdown-menu">
                            <a href="honor2.html">个人荣誉墙</a>
                        </div>
                    </div>
                    <div class="relative dropdown">
                        <a class="nav-link">训练活动 <i class="fas fa-chevron-down text-xs ml-0.5 opacity-60"></i></a>
                        <div class="dropdown-menu">
                            <a href="volunteer.html">志愿活动</a>
                        </div>
                    </div>
                    <div class="relative dropdown">
                        <a class="nav-link">服务中心 <i class="fas fa-chevron-down text-xs ml-0.5 opacity-60"></i></a>
                        <div class="dropdown-menu">
                            <a href="regulations.html">规章制度</a>
                            <a href="downloads.html">资料下载</a>
                            <a href="faq.html">常见问题</a>
                        </div>
                    </div>
                    <a href="join.html" class="nav-link">招贤纳士</a>
                </nav>

            <!-- 移动端汉堡菜单按钮 -->
            <button id="menuToggle" class="lg:hidden w-9 h-9 flex items-center justify-center rounded hover:bg-gray-100 active:bg-gray-200 transition-colors duration-200" onclick="toggleMobileMenu()">
                <i class="fas fa-bars text-gray-700 text-base"></i>
            </button>
        </div>
    </div>
</header>

<!-- 移动端侧边菜单 -->
<div id="mobileMenuOverlay" class="fixed inset-0 bg-black/25 z-[60] hidden opacity-0 transition-opacity duration-400" onclick="toggleMobileMenu()"></div>

<div id="mobileMenu" class="mobile-menu overflow-y-auto">
    <div class="p-5 border-b border-gray-100">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-pkured font-serif">导航菜单</h2>
            <button onclick="toggleMobileMenu()" class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors">
                <i class="fas fa-times text-gray-500"></i>
            </button>
        </div>
    </div>

    <nav class="p-3 space-y-0.5">
            <a href="index.html" class="block px-5 py-3 text-gray-700 hover:bg-gray-50 hover:text-pkured rounded-md transition-all mobile-menu-item">首页</a>
            <a href="about.html" class="block px-5 py-3 text-gray-700 hover:bg-gray-50 hover:text-pkured rounded-md transition-all mobile-menu-item">大队简介</a>
            <a href="org.html" class="block px-5 py-3 text-gray-700 hover:bg-gray-50 hover:text-pkured rounded-md transition-all mobile-menu-item">组织架构</a>
            <a href="news.php" class="block px-5 py-3 text-pkured font-semibold bg-red-50 rounded-md transition-all mobile-menu-item">通知公告</a>

            <div class="pt-2 pb-1">
                <p class="px-4 text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">人员信息</p>
                <a href="current.html" class="block pl-8 pr-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-pkured rounded-md transition-all mobile-menu-item">第十五届干部</a>
                <a href="history.html" class="block pl-8 pr-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-pkured rounded-md transition-all mobile-menu-item">历任干部</a>
            </div>

            <div class="pt-2 pb-1">
                <p class="px-4 text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">荣誉墙</p>
                <a href="honor2.html" class="block pl-8 pr-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-pkured rounded-md transition-all mobile-menu-item">个人荣誉墙</a>
            </div>
            <div class="pt-2 pb-1">
                <p class="px-4 text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">训练活动</p>
                <a href="volunteer.html" class="block pl-8 pr-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-pkured rounded-md transition-all mobile-menu-item">志愿活动</a>
            </div>
            <div class="pt-2 pb-1">
                <p class="px-4 text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">服务中心</p>
                <a href="regulations.html" class="block pl-8 pr-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-pkured rounded-md transition-all mobile-menu-item">规章制度</a>
                <a href="downloads.html" class="block pl-8 pr-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-pkured rounded-md transition-all mobile-menu-item">资料下载</a>
                <a href="faq.html" class="block pl-8 pr-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-pkured rounded-md transition-all mobile-menu-item">常见问题</a>
            </div>
            <a href="join.html" class="block px-5 py-3 text-gray-700 hover:bg-gray-50 hover:text-pkured rounded-md transition-all mobile-menu-item">招贤纳士</a>
        </nav>
</div>

<main>
<section class="py-12 bg-cloud"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

<?php if (empty($allNews)): ?>
<div class="text-center py-12 text-gray-400">
    <p class="text-lg mb-2">暂无通知公告数据</p>
    <p class="text-sm">请稍后再试，或联系管理员添加内容。</p>
</div>
<?php else: ?>

<?php if ($featuredNotice): ?>
<div class="rounded-3xl bg-white overflow-hidden shadow-lg border border-red-100 mb-8">
    <div class="p-6 md:p-8">
        <span class="notice-tag">新闻动态</span>
        <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mt-4 mb-3"><?php echo htmlspecialchars($featuredNotice['title']); ?></h2>
        <p class="text-gray-600 leading-8 mb-5"><?php echo htmlspecialchars($featuredNotice['desc'] ?? ''); ?></p>
        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 mb-6"><span><?php echo htmlspecialchars($featuredNotice['date']); ?></span><span><?php echo htmlspecialchars($featuredNotice['author'] ?? '宣传部'); ?></span><span><?php echo (int)($featuredNotice['views'] ?? 0); ?>次浏览</span></div>
        <a href="news-detail.php?id=<?php echo (int)$featuredNotice['id']; ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-pkured text-white text-sm font-semibold hover:bg-pkured-dark transition-colors">查看详情 <i class="fas fa-arrow-right text-xs"></i></a>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5" id="newsList">
<?php foreach ($pagedNews as $item): ?>
<div class="notice-card" style="cursor:pointer;"
     onclick="window.location.href='news-detail.php?id=<?php echo (int)$item['id']; ?>'">
    <div class="flex justify-between items-start gap-4 mb-3">
        <span class="notice-tag">新闻动态</span>
        <span class="text-sm text-gray-500 whitespace-nowrap"><?php echo htmlspecialchars($item['date']); ?></span>
    </div>
    <h3 class="text-xl font-bold text-gray-900 mb-3 leading-snug"><?php echo htmlspecialchars($item['title']); ?></h3>
    <p class="text-gray-600 mb-4 leading-7"><?php echo htmlspecialchars($item['desc'] ?? ''); ?></p>
    <div class="flex items-center gap-4 text-sm text-gray-500 border-t border-gray-100 pt-4">
        <span><?php echo htmlspecialchars($item['author'] ?? '宣传部'); ?></span>
        <span><?php echo (int)($item['views'] ?? 0); ?>次浏览</span>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- 分页由PHP生成 -->
<div class="mt-8 flex justify-center gap-2" id="newsPagination">
    <?php if ($page > 1): ?>
        <a href="?p=1" class="page-btn">首页</a>
        <a href="?p=<?php echo $page-1; ?>" class="page-btn">上一页</a>
    <?php endif; ?>

    <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
        <a href="?p=<?php echo $i; ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a href="?p=<?php echo $page+1; ?>" class="page-btn">下一页</a>
        <a href="?p=<?php echo $totalPages; ?>" class="page-btn">末页</a>
    <?php endif; ?>

    <span class="ml-4 text-sm text-gray-500">共 <strong class="text-pkured"><?php echo $totalPages; ?></strong> 页</span>
</div>

<?php endif; ?>

</div></section>
</main>

<footer class="bg-gray-900 text-gray-300 py-12 md:py-14">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 md:gap-8 mb-10">
            <!-- Logo与队训 -->
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <img src="/logo.jpg" alt="Logo" class="w-11 h-11 rounded-lg object-cover" onerror="this.style.display='none'">
                    <div>
                        <h3 class="text-lg font-bold font-serif text-white">学生志愿教导大队</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-400 leading-relaxed">明理严军 · 自强不息</p>
            </div>

            <!-- 快速链接 -->
            <div>
                <h4 class="text-sm font-semibold mb-4 text-white uppercase tracking-wider">快速链接</h4>
                <ul class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <li><a href="index.html" class="hover:text-white transition-colors">首页</a></li>
                        <li><a href="about.html" class="hover:text-white transition-colors">大队简介</a></li>
                        <li><a href="org.html" class="hover:text-white transition-colors">组织架构</a></li>
                        <li><a href="news.php" class="hover:text-white transition-colors">通知公告</a></li>
                        <li><a href="volunteer.html" class="hover:text-white transition-colors">志愿活动</a></li>
                        <li><a href="current.html" class="hover:text-white transition-colors">第十五届干部</a></li>
                        <li><a href="history.html" class="hover:text-white transition-colors">历任干部</a></li>
                        <li><a href="honor2.html" class="hover:text-white transition-colors">个人荣誉墙</a></li>
                        <li><a href="regulations.html" class="hover:text-white transition-colors">规章制度</a></li>
                        <li><a href="downloads.html" class="hover:text-white transition-colors">资料下载</a></li>
                        <li><a href="faq.html" class="hover:text-white transition-colors">常见问题</a></li>
                        <li><a href="join.html" class="hover:text-white transition-colors">招贤纳士</a></li>
                    </ul>
            </div>

            <!-- 联系方式与社交 -->
            <div>
                <h4 class="text-sm font-semibold mb-4 text-white uppercase tracking-wider">联系方式</h4>
                <div class="space-y-2.5 text-sm text-gray-400 mb-6">
                    <p><strong class="text-gray-300">办公地址：</strong>湖南涉外经济学院南四栋1楼102室</p>
                    <p><strong class="text-gray-300">工作时间：</strong>周一至周五 9:00-19:00</p>
                </div>

                <div class="flex items-center gap-5">
                    <div class="social-icon-wrapper">
                        <i class="fab fa-weixin text-lg text-gray-400 hover:text-white cursor-pointer transition-colors"></i>
                        <div class="qr-popup">
                            <img src="/wechat-qr.jpg" alt="微信二维码" class="w-24 h-24 rounded mx-auto block" onerror="this.replaceWith(this.nextElementSibling || document.createElement('span'))">
                            <p class="text-[10px] text-gray-500 mt-1.5">扫一扫关注公众号</p>
                        </div>
                    </div>
                    <div class="social-icon-wrapper">
                        <i class="fab fa-tiktok text-lg text-gray-400 hover:text-white cursor-pointer transition-colors"></i>
                        <div class="qr-popup">
                            <img src="/douyin-qr.jpg" alt="抖音二维码" class="w-24 h-24 rounded mx-auto block" onerror="this.replaceWith(this.nextElementSibling || document.createElement('span'))">
                            <p class="text-[10px] text-gray-500 mt-1.5">扫一扫关注官方抖音</p>
                        </div>
                    </div>
                    <div class="social-icon-wrapper">
                        <i class="fab fa-weibo text-lg text-gray-400 hover:text-white cursor-pointer transition-colors"></i>
                        <div class="qr-popup">
                            <img src="/weibo-qr.jpg" alt="微博二维码" class="w-24 h-24 rounded mx-auto block" onerror="this.replaceWith(this.nextElementSibling || document.createElement('span'))">
                            <p class="text-[10px] text-gray-500 mt-1.5">扫一扫关注官方微博</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-800 pt-8 text-center text-xs text-gray-500 space-y-2">
            <p>学生志愿教导大队版权所有</p>
            <p class="flex flex-col sm:flex-row items-center justify-center gap-x-4 gap-y-1">
                <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer" class="hover:text-gray-300 transition-colors cursor-pointer">
                    蜀ICP备2026028492号-1
                </a>
                <a href="http://www.beian.gov.cn/portal/registerSystemInfo?recordcode=51012202002408" target="_blank" rel="noopener noreferrer" class="hover:text-gray-300 transition-colors cursor-pointer inline-flex items-center gap-1">
                    <img src="https://www.beian.gov.cn/img/ghs.png" alt="公安备案图标" class="w-4 h-4">
                    川公网安备51012202002408号
                </a>
            </p>
        </div>
    </div>
</footer>

<script>function toggleMobileMenu(){const m=document.getElementById('mobileMenu'),o=document.getElementById('mobileMenuOverlay');if(m.classList.contains('menu-open')){m.style.transform='translateX(100%)';o.style.opacity='0';setTimeout(()=>{m.classList.remove('menu-open');o.classList.add('hidden')},400)}else{o.classList.remove('hidden');setTimeout(()=>{o.style.opacity='1';m.classList.add('menu-open');m.style.transform='translateX(0)'},10)}}(function(){var bp=document.createElement('script');bp.src=(location.protocol==='https')?'https://zz.bdstatic.com/linksubmit/push.js':'http://push.zhanzhang.baidu.com/push.js';var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(bp,s)}());(function(){if('serviceWorker'in navigator)navigator.serviceWorker.register('/sw.js',{scope:'/'}).then(()=>{}).catch(e=>{})})();</script>
</body>
</html>
