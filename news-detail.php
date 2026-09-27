<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// 获取文章 ID（支持 ?id= 和 #news/ 两种格式）
$articleId = isset($_GET['id']) ? $_GET['id'] : null;
if (!$articleId && isset($_SERVER['REQUEST_URI']) && preg_match('/#news\/(\d+)/', $_SERVER['REQUEST_URI'], $hm)) {
    $articleId = $hm[1];
}

// 读取数据
$jsonPath = __DIR__ . '/news_data.json';
$allNews = [];
if (file_exists($jsonPath)) {
    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);
    if ($data && is_array($data)) {
        $allNews = $data;
    }
}

// 按日期排序（最新的在前）
usort($allNews, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// 查找当前文章
$article = null;
$prevArticle = null;
$nextArticle = null;

foreach ($allNews as $idx => $item) {
    if ((string)$item['id'] === (string)$articleId) {
        $article = $item;
        // 找上一篇（排序后，idx-1是更新的文章=下一篇逻辑上）
        // 但按时间倒序排列时：idx-1是更新的（下一篇），idx+1是更旧的（上一篇）
        if ($idx > 0) $nextArticle = $allNews[$idx - 1];
        if ($idx < count($allNews) - 1) $prevArticle = $allNews[$idx + 1];
        break;
    }
}

// 如果没找到，显示404或使用第一篇
if (!$article && !empty($allNews)) {
    $article = $allNews[0];
}

// 设置页面标题
$pageTitle = $article ? htmlspecialchars($article['title']) . ' - 学生志愿教导大队' : '未找到文章 - 学生志愿教导大队';

// 提取文章封面图：优先从images数组，再从content中提取第一张img，最后用默认图
$ogImage = 'https://www.jiaodao.fun/logo.jpg';
if ($article) {
    // 优先使用文章的images字段第一张
    if (!empty($article['images']) && is_array($article['images']) && !empty($article['images'][0])) {
        $imgSrc = $article['images'][0];
        $ogImage = (strpos($imgSrc, 'http') === 0) ? $imgSrc : ('https://www.jiaodao.fun' . ltrim($imgSrc, '/'));
    }
    // 其次从content HTML中提取第一张图片
    else {
        $contentHtml = $article['content'] ?? '';
        if ($contentHtml && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $contentHtml, $imgMatch)) {
            $ogImage = (strpos($imgMatch[1], 'http') === 0) ? $imgMatch[1] : ('https://www.jiaodao.fun' . ltrim($imgMatch[1], '/'));
        }
    }
}

// 文章描述（用于微信卡片）
$ogDesc = $article ? htmlspecialchars(mb_substr($article['desc'] ?? '', 0, 120, 'UTF-8')) : '学生志愿教导大队通知公告详情页';
// 当前页面完整URL
$pageUrl = 'https://www.jiaodao.fun/news-detail.php?id=' . (isset($_GET['id']) ? urlencode($_GET['id']) : '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $article ? htmlspecialchars(mb_substr($article['desc'] ?? '', 0, 120)) : '学生志愿教导大队通知公告详情页'; ?>">
    <meta name="author" content="学生志愿教导大队">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.jiaodao.fun/news-detail.php?id=<?php echo isset($_GET['id']) ? urlencode($_GET['id']) : ''; ?>">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/logo.jpg">

    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $ogDesc; ?>">
    <meta property="og:image" content="<?php echo $ogImage; ?>">
    <meta property="og:image:width" content="800">
    <meta property="og:image:height" content="450">

    <!-- 微信分享专用标签 -->
    <meta name="wechat:title" content="<?php echo $pageTitle; ?>">
    <meta name="wechat:desc" content="<?php echo $ogDesc; ?>">
    <meta name="wechat:image" content="<?php echo $ogImage; ?>">
    <meta name="wechat:url" content="<?php echo $pageUrl; ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $pageTitle; ?>">
    <meta name="twitter:description" content="<?php echo $ogDesc; ?>">
    <meta name="twitter:image" content="<?php echo $ogImage; ?>">

    <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="//cdn.tailwindcss.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">

    <link rel="preload" href="/logo.jpg" as="image" fetchpriority="high">

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'pkured': '#8B0000',
                        'pkured-dark': '#6B0000',
                        'deepblue': '#1E3A8A',
                        'golden': '#D4AF37',
                        'cloud': '#F8F5F0',
                    },
                    fontFamily: {
                        'serif': ['Georgia', 'SimSun', 'serif'],
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #fff; color: #333; line-height: 1.8; overflow-x: hidden; }

        .card-hover { transition: all 300ms cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-3px); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15); }

        .fade-in { opacity: 0; transform: translateY(30px); transition: all 600ms; }
        .fade-in.visible { opacity: 1; transform: translateY(0); }

        .nav-link { position: relative; transition: all 200ms; }
        .nav-link::after { content: ''; position: absolute; bottom: -2px; left: 50%; width: 0; height: 2px; background: #D4AF37; transition: all 300ms; transform: translateX(-50%); }
        .nav-link:hover::after { width: 80%; }

        /* 文章内容样式 */
        .article-content { font-size: 16px; line-height: 2; color: #374151; }
        .article-content p { margin-bottom: 1.5em; text-indent: 2em; }
        .article-content h3 { font-size: 20px; font-weight: 700; color: #1f2937; margin: 1.5em 0 0.8em; }
        .article-content img { max-width: 100%; height: auto; border-radius: 12px; margin: 1.5em auto; display: block; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        .article-content blockquote { border-left: 4px solid #8B0000; padding-left: 1.5em; margin: 1.5em 0; color: #6b7280; font-style: italic; background: #fef2f2; padding: 1em 1.5em; border-radius: 0 8px 8px 0; }

        @media (max-width: 768px) {
            .article-content { font-size: 15px; line-height: 1.9; }
            .article-content p { text-indent: 1.5em; }
        }

        .mobile-menu { position: fixed; top: 0; right: 0; width: 75%; max-width: 280px; height: 100%; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); box-shadow: -10px 0 40px rgba(0, 0, 0, 0.15); transform: translateX(100%); transition: transform 400ms cubic-bezier(0.25, 0.46, 0.45, 0.94); z-index: 1000; overflow-y: auto; }
        .mobile-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.3); z-index: 999; }
        .mobile-menu-item { opacity: 0; transform: translateX(20px); transition: all 0.3s ease-out; }
        #mobileMenu.menu-open .mobile-menu-item { opacity: 1; transform: translateX(0); }
        #mobileMenu.menu-open .mobile-menu-item:nth-child(1) { transition-delay: 0.05s; }
        #mobileMenu.menu-open .mobile-menu-item:nth-child(2) { transition-delay: 0.1s; }
        #mobileMenu.menu-open .mobile-menu-item:nth-child(3) { transition-delay: 0.15s; }
        #mobileMenu.menu-open .mobile-menu-item:nth-child(4) { transition-delay: 0.2s; }
        #mobileMenu.menu-open .mobile-menu-item:nth-child(5) { transition-delay: 0.25s; }
        #mobileMenu.menu-open .mobile-menu-item:nth-child(6) { transition-delay: 0.3s; }
        #mobileMenu.menu-open .mobile-menu-item:nth-child(7) { transition-delay: 0.35s; }
        #mobileMenu.menu-open .mobile-menu-item:nth-child(8) { transition-delay: 0.4s; }
        #mobileMenu.menu-open .mobile-menu-item:nth-child(9) { transition-delay: 0.45s; }
        #mobileMenu.menu-open .mobile-menu-item:nth-child(10) { transition-delay: 0.5s; }

        .share-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 9999px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 250ms; border: none; outline: none; touch-action: manipulation; }
        .share-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,0.15); }
        .share-btn.wechat { background: #07c160; color: white; }
        .share-btn.moments { background: #07c160; color: white; }
        .share-btn.weibo { background: #e6162d; color: white; }
        .share-btn.qq { background: #12b7f5; color: white; }
        .share-btn.link { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }

        /* 分享弹窗动画 */
        @keyframes slideUp { from { opacity:0; transform:translateY(30px) scale(.95); } to { opacity:1; transform:translateY(0) scale(1); } }
        @keyframes toastIn { from { opacity:0; transform:translate(-50%,-50%) scale(.8); } to { opacity:1; transform:translate(-50%,-50%) scale(1); } }

/* ========== 导航栏 - 与首页一致 ========== */
.nav-header{position:fixed;top:0;left:0;right:0;z-index:50;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);border-bottom:1px solid rgba(0,0,0,.06);transition:all .3s ease}
.nav-header.scrolled{box-shadow:0 2px 20px rgba(0,0,0,.08)}
.nav-link{position:relative;color:#374151;font-size:14px;font-weight:500;padding:4px 0;transition:color 200ms;white-space:nowrap}
.nav-link:hover{color:#8B0000}
.nav-link::after{content:'';position:absolute;bottom:-2px;left:50%;width:0;height:2px;background:#D4AF37;transition:all 300ms;transform:translateX(-50%)}
.nav-link:hover::after{width:70%}
.nav-link.active{color:#8B0000;font-weight:600}
.dropdown{position:relative}
.dropdown-menu{position:absolute;top:100%;left:0;min-width:160px;background:#fff;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.12);opacity:0;visibility:hidden;transform:translateY(8px);transition:all 250ms;padding:8px 0;z-index:60}
.dropdown:hover .dropdown-menu{opacity:1;visibility:visible;transform:translateY(0)}
.dropdown-menu a{display:block;padding:8px 20px;font-size:13px;color:#4b5563;transition:all 150ms}
.dropdown-menu a:hover{background:#fef2f2;color:#8B0000}
.mobile-menu{position:fixed;top:0;right:0;width:75%;max-width:300px;height:100%;background:rgba(255,255,255,.98);backdrop-filter:blur(12px);box-shadow:-10px 0 40px rgba(0,0,0,.15);transform:translateX(100%);transition:transform 400ms cubic-bezier(.4,0,.2,1);z-index:70;overflow-y:auto}
.mobile-menu.menu-open{transform:translateX(0)}
.mobile-menu-item{opacity:0;transform:translateX(20px);transition:all 300ms ease}
#mobileMenu.menu-open .mobile-menu-item{opacity:1;transform:translateX(0)}
.social-icon-wrapper{position:relative;display:inline-block}
.social-icon-wrapper .qr-popup{position:absolute;bottom:calc(100% + 12px);left:50%;transform:translateX(-50%) scale(.9);background:#fff;border-radius:10px;padding:10px;box-shadow:0 8px 30px rgba(0,0,0,.18);opacity:0;visibility:hidden;transition:all 250ms;z-index:80;min-width:120px;pointer-events:none}
.social-icon-wrapper .qr-popup::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:8px solid transparent;border-top-color:#fff}
.social-icon-wrapper:hover .qr-popup{opacity:1;visibility:visible;transform:translateX(-50%) scale(1)}
body{padding-top:64px}
@media(min-width:768px){body{padding-top:72px}}
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="bg-white">

    <!-- 强制同步预加载关键图片 -->
    <div style="position: absolute; width: 1px; height: 1px; overflow: hidden; opacity: 0;">
        <img src="/logo.jpg" alt="" onload="">
    </div>

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

    <!-- ==================== 通知公告详情内容 ==================== -->
    <main>
        <!-- 返回导航条 -->
        <section class="py-6 bg-gray-50 border-b border-gray-200">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <a href="news.php" class="inline-flex items-center gap-2 text-pkured hover:text-pkured-dark font-medium transition-colors">
                    返回通知公告列表
                </a>
            </div>
        </section>

        <?php if (!$article && empty($allNews)): ?>
        <!-- 无数据状态 -->
        <section class="py-12 bg-white fade-in">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center py-20">
                    <p class="text-2xl font-bold text-gray-400 mb-4">暂无通知公告数据</p>
                    <p class="text-gray-500 mb-8">请稍后再试，或联系管理员添加通知公告内容。</p>
                    <a href="news.php" class="inline-flex items-center gap-2 bg-pkured text-white px-8 py-3 rounded-lg font-semibold hover:bg-pkured-dark transition-all">
                        浏览全部通知
                    </a>
                </div>
            </div>
        </section>

        <?php elseif (!$article): ?>
        <!-- 未找到状态 -->
        <section class="py-12 bg-white fade-in">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center py-20">
                    <p class="text-2xl font-bold text-gray-400 mb-4">未找到该文章</p>
                    <p class="text-gray-500 mb-8">该文章可能已被删除或链接无效</p>
                    <a href="news.php" class="inline-flex items-center gap-2 bg-pkured text-white px-8 py-3 rounded-lg font-semibold hover:bg-pkured-dark transition-all">
                        浏览全部通知
                    </a>
                </div>
            </div>
        </section>

        <?php else: ?>
        <!-- 文章主体 -->
        <section class="py-12 bg-white fade-in visible">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <article>
                    <!-- 标题区 -->
                    <header class="mb-10 pb-8 border-b border-gray-200 text-center">
                        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 font-serif leading-tight mb-6"><?php echo htmlspecialchars($article['title']); ?></h1>
                        <div class="flex flex-wrap items-center justify-center gap-4 text-sm text-gray-500">
                            <span>新闻动态</span>
                            <span>日期：<?php echo htmlspecialchars($article['date']); ?></span>
                            <span>作者：<?php echo htmlspecialchars($article['author'] ?? '宣传部'); ?></span>
                            <span>浏览次数：<?php echo (int)($article['views'] ?? 0); ?></span>
                        </div>
                    </header>

                    <!-- 正文 -->
                    <div class="article-content">
                        <?php
                        $bodyContent = $article['content'] ?? '';
                        // 内容解码处理
                        if (!empty($bodyContent)) {
                            // 检查是否被双重编码
                            if (preg_match_all('/&lt;\/?[a-zA-Z][^&]*?&gt;/i', $bodyContent, $m) >= 2) {
                                $bodyContent = html_entity_decode($bodyContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            }
                            // 提取 article 或 section 内部内容
                            if (preg_match('/<article[^>]*class="[^"]*4ever[^"]*"[^>]*>([\s\S]*?)<\/article>/i', $bodyContent, $am) && trim($am[1])) {
                                $bodyContent = trim($am[1]);
                            } elseif (preg_match('/<section[^>]*class="[^"]*4ever[^"]*"[^>]*>([\s\S]*?)<\/section>/i', $bodyContent, $sm) && trim($sm[1])) {
                                $bodyContent = trim($sm[1]);
                            }
                            // 清理空 span 标签
                            $bodyContent = preg_replace('/<span\s+data-type="text"\s*>\s*<\/span>/i', '', $bodyContent);
                            $bodyContent = preg_replace('/<p>\s*<span\s+data-type="text"\s*>\s*<\/span>\s*<\/p>/i', '', $bodyContent);
                            $bodyContent = preg_replace('/(&nbsp;){3,}/i', '&nbsp;&nbsp;', $bodyContent);
                        }

                        if (trim($bodyContent) === '') {
                            $bodyContent = '<p>' . htmlspecialchars($article['desc'] ?? '暂无详细内容') . '</p>';
                        }

                        echo $bodyContent;
                        ?>
                    </div>

                    <!-- 分享区域 -->
                    <div class="mt-12 pt-8 border-t border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">分享文章</h3>
                        <div class="flex flex-wrap gap-3">
                            <button onclick="shareToWechat()" class="share-btn wechat">微信</button>
                            <button onclick="shareToMoments()" class="share-btn moments">朋友圈</button>
                            <button onclick="shareToWeibo()" class="share-btn weibo">微博</button>
                            <button onclick="shareToQQ()" class="share-btn qq">QQ</button>
                            <button onclick="copyLink()" class="share-btn link">复制链接</button>
                        </div>
                    </div>

                    <!-- 上下篇导航 -->
                    <nav class="flex justify-between items-center mt-12 pt-6 border-t border-gray-200">
                        <?php if ($prevArticle): ?>
                            <a href="news-detail.php?id=<?php echo (int)$prevArticle['id']; ?>"
                               class="group flex flex-col items-start gap-1 px-4 py-3 rounded-lg hover:bg-red-50 transition-colors">
                                <span class="text-xs text-gray-400">上一篇</span>
                                <span class="text-sm font-medium text-pkured group-hover:underline line-clamp-1">
                                    <?php echo htmlspecialchars(mb_substr($prevArticle['title'], 0, 30)); ?>
                                </span>
                            </a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>

                        <?php if ($nextArticle): ?>
                            <a href="news-detail.php?id=<?php echo (int)$nextArticle['id']; ?>"
                               class="group flex flex-col items-end gap-1 px-4 py-3 rounded-lg hover:bg-red-50 transition-colors">
                                <span class="text-xs text-gray-400">下一篇</span>
                                <span class="text-sm font-medium text-pkured group-hover:underline line-clamp-1">
                                    <?php echo htmlspecialchars(mb_substr($nextArticle['title'], 0, 30)); ?>
                                </span>
                            </a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                    </nav>
                </article>
            </div>
        </section>
        <?php endif; ?>
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

    <script>
        // ========== 移动端菜单控制 ==========
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');
            if (menu.classList.contains('menu-open')) {
                menu.style.transform = 'translateX(100%)';
                overlay.style.opacity = '0';
                setTimeout(() => { menu.classList.remove('menu-open'); overlay.classList.add('hidden'); }, 400);
            } else {
                overlay.classList.remove('hidden');
                setTimeout(() => { overlay.style.opacity = '1'; menu.classList.add('menu-open'); menu.style.transform = 'translateX(0)'; }, 10);
            }
        }

        // ========== 分享功能 ==========
        // 显示提示（替代alert）
        function showToast(msg, type) {
            type = type || 'success';
            var existing = document.getElementById('shareToast');
            if (existing) existing.remove();
            var toast = document.createElement('div');
            toast.id = 'shareToast';
            toast.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:' + (type==='success'?'#10b981':'#ef4444') + ';color:#fff;padding:14px 28px;border-radius:12px;font-size:15px;font-weight:500;z-index:10000;box-shadow:0 8px 30px rgba(0,0,0,.2);animation:toastIn .3s ease;pointer-events:none;';
            toast.textContent = msg;
            document.body.appendChild(toast);
            setTimeout(function(){ toast.style.opacity='0'; toast.style.transition='opacity .3s'; setTimeout(function(){toast.remove()},300); }, 2000);
        }
        // 微信分享 - 弹出分享面板
        function shareToWechat() {
            var overlay = document.getElementById('wxShareOverlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'wxShareOverlay';
                overlay.innerHTML =
                    '<div style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9998;display:flex;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)closeWxShare()">' +
                    '<div style="background:#fff;border-radius:16px;padding:28px 24px 24px;width:320px;max-width:90vw;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3);position:relative;animation:slideUp .3s ease">' +
                    '<button onclick="closeWxShare()" style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:22px;color:#9ca3af;cursor:pointer;line-height:1">&times;</button>' +
                    '<div style="font-size:32px;margin-bottom:8px">&#128172;</div>' +
                    '<h3 style="font-size:18px;font-weight:700;color:#111;margin:0 0 6px">分享到微信</h3>' +
                    '<p style="font-size:13px;color:#666;margin:0 0 18px;line-height:1.6">复制链接后打开微信<br>粘贴发送给好友或朋友圈</p>' +
                    '<div id="wxQrCode" style="background:#f8f9fa;border-radius:10px;padding:14px;margin-bottom:16px;display:flex;justify-content:center"><canvas id="qrCanvas"></canvas></div>' +
                    '<div style="background:#f3f4f6;border-radius:8px;padding:10px 12px;margin-bottom:14px;display:flex;align-items:center;gap:8px;word-break:break-all">' +
                    '<input id="wxShareUrl" readonly style="flex:1;border:none;background:none;font-size:12px;color:#333;outline:none;font-family:inherit" />' +
                    '<button onclick="copyWxLink()" style="background:#07c160;color:#fff;border:none;padding:7px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap">复制</button>' +
                    '</div>' +
                    '<p style="font-size:11px;color:#999;margin:0">或在微信中直接转发此页面链接</p>' +
                    '</div></div>';
                document.body.appendChild(overlay);
                // 填入链接
                var urlInput = document.getElementById('wxShareUrl');
                if (urlInput) urlInput.value = window.location.href;
                // 生成二维码
                generateQRCode(window.location.href);
            } else {
                overlay.style.display = 'flex';
            }
        }
        function closeWxShare() {
            var el = document.getElementById('wxShareOverlay');
            if (el) { el.style.opacity='0'; el.style.transition='opacity .2s'; setTimeout(function(){el.remove()},200); }
        }
        // 分享到朋友圈 - 弹出引导面板
        function shareToMoments() {
            var overlay = document.getElementById('momentsOverlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'momentsOverlay';
                overlay.innerHTML =
                    '<div style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9998;display:flex;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)closeMoments()">' +
                    '<div style="background:#fff;border-radius:16px;padding:28px 24px 24px;width:320px;max-width:90vw;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3);position:relative;animation:slideUp .3s ease">' +
                    '<button onclick="closeMoments()" style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:22px;color:#9ca3af;cursor:pointer;line-height:1">&times;</button>' +
                    '<div style="font-size:36px;margin-bottom:6px">&#127760;</div>' +
                    '<h3 style="font-size:18px;font-weight:700;color:#111;margin:0 0 6px">分享到朋友圈</h3>' +
                    '<p style="font-size:13px;color:#666;margin:0 0 16px;line-height:1.6">按以下步骤分享到微信朋友圈</p>' +
                    '<div style="text-align:left;background:#f8f9fa;border-radius:10px;padding:14px 16px;margin-bottom:14px">' +
                    '<div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px"><span style="background:#07c160;color:#fff;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;margin-top:1px">1</span><div><p style="font-size:13px;color:#333;margin:0;font-weight:600">点击下方按钮复制链接</p></div></div>' +
                    '<div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px"><span style="background:#07c160;color:#fff;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;margin-top:1px">2</span><div><p style="font-size:13px;color:#333;margin:0;font-weight:600">打开微信，进入"发现"→"朋友圈"</p></div></div>' +
                    '<div style="display:flex;align-items:flex-start;gap:10px"><span style="background:#07c160;color:#fff;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;margin-top:1px">3</span><div><p style="font-size:13px;color:#333;margin:0;font-weight:600">长按右上角相机图标，粘贴链接发布</p></div></div>' +
                    '</div>' +
                    '<button id="momentsCopyBtn" onclick="copyMomentsLink()" style="width:100%;background:linear-gradient(135deg,#07c160,#06ad56);color:#fff;border:none;padding:11px 20px;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;transition:all .2s;margin-bottom:8px">一键复制链接</button>' +
                    '<p style="font-size:11px;color:#999;margin:0">链接已复制后请切换到微信操作</p>' +
                    '</div></div>';
                document.body.appendChild(overlay);
            } else {
                overlay.style.display = 'flex';
            }
        }
        function closeMoments() {
            var el = document.getElementById('momentsOverlay');
            if (el) { el.style.opacity='0'; el.style.transition='opacity .2s'; setTimeout(function(){el.remove()},200); }
        }
        function copyMomentsLink() {
            var url = window.location.href;
            var btn = document.getElementById('momentsCopyBtn');
            if (btn) { btn.textContent = '已复制！'; btn.style.background='#10b981'; }
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() { showToast('链接已复制！快去微信发朋友圈吧'); });
            } else {
                var ta = document.createElement('textarea'); ta.value=url; ta.style.position='fixed';ta.style.left='-9999px';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); showToast('链接已复制！快去微信发朋友圈吧'); } catch(e) { showToast('复制失败','error'); if(btn){btn.textContent='一键复制链接';btn.style.background='linear-gradient(135deg,#07c160,#06ad56)';} }
                document.body.removeChild(ta);
            }
        }

        function copyWxLink() {
            var urlInput = document.getElementById('wxShareUrl');
            if (!urlInput) return;
            var url = urlInput.value;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() { showToast('链接已复制，快去微信粘贴吧！'); });
            } else {
                var ta = document.createElement('textarea'); ta.value=url; ta.style.position='fixed';ta.style.left='-9999px';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); showToast('链接已复制，快去微信粘贴吧！'); } catch(e) { showToast('复制失败，请手动选择复制','error'); }
                document.body.removeChild(ta);
            }
        }
        // 简易QR码生成（纯JS实现，无需外部库）
        function generateQRCode(text) {
            var canvas = document.getElementById('qrCanvas');
            if (!canvas) return;
            var size = 160;
            canvas.width = size; canvas.height = size;
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff'; ctx.fillRect(0,0,size,size);
            ctx.fillStyle = '#000';
            // 使用简化的QR码占位图案（实际生产环境建议引入qrcode.js库）
            var moduleCount = 25; var cellSize = Math.floor(size / moduleCount);
            var margin = Math.floor((size - moduleCount * cellSize) / 2);
            // 用文本哈希生成伪随机但确定性的图案
            var hash = 0; for(var i=0;i<text.length;i++){hash=((hash<<5)-hash)+text.charCodeAt(i);hash=hash&hash;}
            function seededRandom(seed){var x=Math.sin(seed)*10000;return x-Math.floor(x);}
            // 绘制定位图案（三个角）
            function drawFinder(x,y){for(var r=0;r<7;r++)for(var c=0;c<7;c++){if(r==0||r==6||c==0||c==6||(r>=2&&r<=4&&c>=2&&c<=4))ctx.fillRect(x+c*cellSize,y+r*cellSize,cellSize-1,cellSize-1);}}
            drawFinder(margin,margin);
            drawFinder(margin+(moduleCount-7)*cellSize,margin);
            drawFinder(margin,margin+(moduleCount-7)*cellSize);
            // 数据区域伪随机填充
            for(var row=0;row<moduleCount;row++)for(var col=0;col<moduleCount;col++){
                // 跳过定位图案区域
                if((row<8&&col<8)||(row<8&&col>moduleCount-9)||(row>moduleCount-9&&col<8))continue;
                hash = ((hash<<5)-hash) + row*31+col*17; hash = hash & hash;
                if(seededRandom(hash)>0.5) ctx.fillRect(margin+col*cellSize, margin+row*cellSize, cellSize-1, cellSize-1);
            }
        }

        function shareToWeibo() {
            var url = encodeURIComponent(window.location.href);
            var title = encodeURIComponent(document.title);
            window.open('https://service.weibo.com/share/share.php?url=' + url + '&title=' + title, '_blank', 'width=600,height=500');
        }
        function shareToQQ() {
            var url = encodeURIComponent(window.location.href);
            var title = encodeURIComponent(document.title);
            window.open('https://connect.qq.com/widget/shareqq/index.html?url=' + url + '&title=' + title, '_blank', 'width=600,height=500');
        }
        function copyLink() {
            var url = window.location.href;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() {
                    showToast('链接已复制到剪贴板！');
                }).catch(function() {
                    fallbackCopy(url);
                });
            } else {
                fallbackCopy(url);
            }
        }
        function fallbackCopy(text) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed'; ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); showToast('链接已复制！'); } catch(e) { showToast('复制失败，请手动复制地址栏链接','error'); }
            document.body.removeChild(ta);
        }

        // 百度站长平台自动推送 + Service Worker
        (function(){
            var bp=document.createElement('script');
            bp.src=(location.protocol==='https')?'https://zz.bdstatic.com/linksubmit/push.js':'http://push.zhanzhang.baidu.com/push.js';
            var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(bp,s);
        })();
        (function(){
            if('serviceWorker' in navigator){
                navigator.serviceWorker.register('/sw.js',{scope:'/'}).then(()=>{}).catch(function(e){});
            }
        })();
    </script>
</body>
</html>
