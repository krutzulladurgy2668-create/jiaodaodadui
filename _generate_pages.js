const fs = require('fs');
const path = require('path');

// ============================================================
// 读取原始 SPA 文件
// ============================================================
const src = fs.readFileSync(path.join(__dirname, 'index.html'), 'utf-8');

// ============================================================
// 提取各部分内容（基于行号）
// ============================================================
const lines = src.split('\n');

function extractLines(start, end) {
    // start 和 end 是1-based行号
    return lines.slice(start - 1, end).join('\n');
}

// ---- CSS 样式块 (lines 162-726) ----
let rawCSS = extractLines(162, 726);

// 删除 SPA 专用的 .page-section 样式
rawCSS = rawCSS.replace(
    /\.page-section\s*\{[^}]*display:\s*none[^}]*\}\s*\.page-section\.active\s*\{[^}]*\}/gs,
    ''
);
rawCSS = rawCSS.replace(
    /\.page-section\s*\{[^}]*animation:\s*pageEnter[^}]*\}\s*@keyframes\s*pageEnter\s*\{[^}]+\}/gs,
    ''
);
// 清理可能残留的空行
rawCSS = rawCSS.replace(/\n{3,}/g, '\n\n');

// ---- Footer 原始 (lines 2049-2140) ----
const rawFooter = extractLines(2049, 2140);
// 将 footer 中的 onclick="showPage('xxx')" 改为 href="xxx.html"
let cleanFooter = rawFooter.replace(/onclick="showPage\('(\w+)'\)"\s+class="/g, 'href="$1.html" class="');

// ---- 页面内容提取 ----
const homeContent = extractLines(864, 1002);       // #home
const aboutContent = extractLines(1005, 1057);      // #about
const orgContent = extractLines(1060, 1198);        // #org
const currentContent = extractLines(1201, 1449);    // #current
const newsListContent = extractLines(1452, 1472);   // #news
const newsDetailContent = extractLines(1475, 1545); // #newsDetail
const historyContent = extractLines(1605, 1688);    // #history
const honor1Content = extractLines(1691, 1771);     // #honor1
const honor2Content = extractLines(1774, 1920);     // #honor2
const joinContent = extractLines(1923, 2047);       // #join

// ---- 首页新闻预览中的 showPage 改链接 ----
let homeClean = homeContent.replace(/onclick="showPage\('(\w+)'\)"/g, 'href="$1.html"');

// ---- 组织架构预览中的 showPage 改链接 ----
homeClean = homeClean.replace(/onclick="showPage\('org\)"/g, 'href="org.html"');

// ---- JS 脚本提取与清理 (lines 2142-3198) ----
let rawJS = extractLines(2142, 3198);

// ============================================================
// 构建清理后的 JS：删除 SPA 函数，修改导航相关函数
// ============================================================
// 我们需要保留的函数列表：
// KEEP: toggleMobileMenu, DEFAULT_NEWS, leadersData, 全局变量
// KEEP: init (修改版), loadNewsData, loadNewsFromServer
// KEEP: 分享轮询系统全部
// KEEP: saveNewsData, _esc, _decodeContent
// KEEP: renderNewsPreview, renderNewsList, renderPagination, goToPage
// KEEP: openNews (修改版), showPrevNews, showNextNews, updateNewsNavButtons
// KEEP: getReviewerByDate, 分享函数全部
// KEEP: backToNewsList (修改版)
// KEEP: initSlider, goToSlide
// KEEP: showLeaderDetail (修改), showMemberDetail (修改)
// KEEP: initScrollAnimations, initStaggerAnimation
// REMOVE: showPage, loadImagesSequentially

// 直接构建新的JS代码
const cleanJS = `
    // ========== 移动端菜单控制 ==========
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('mobileMenuOverlay');
        
        if (menu.classList.contains('menu-open')) {
            menu.style.transform = 'translateX(100%)';
            overlay.style.opacity = '0';
            setTimeout(() => {
                menu.classList.remove('menu-open');
                overlay.classList.add('hidden');
            }, 400);
        } else {
            overlay.classList.remove('hidden');
            setTimeout(() => {
                overlay.style.opacity = '1';
                menu.classList.add('menu-open');
                menu.style.transform = 'translateX(0)';
            }, 10);
        }
    }

    // ========== 默认新闻数据（从管理后台同步，初始为空） ==========
    const DEFAULT_NEWS [];
           
    // ========== 领导数据 ==========
    const leadersData = {
        1: {
            name: '徐京都',
            position: '校长助理、学生工作处处长',
            img: '/leader1.jpg',
            experience: \`<p>徐京都，男，汉族，中共党员，现任湖南涉外经济学院校长助理、主管学生工作部（处）。曾任郑州工商学院书院负责人，信息工程学院党总支副书记，湖南涉外经济学院学工处副处长。</p>\`
        },
        2: {
            name: '夏建平',
            position: '武装部副部长、教导大队指导老师',
            img: '/leader2.jpg',
            experience: \`<p>夏建平，男，汉族，中共党员，现任湖南涉外经济学院武装部副部长、学生志愿教导大队指导老师。负责学校国防教育、军训工作、民兵建设等工作。</p>\`
        }
    };

    // ========== 全局变量 ==========
    let newsData = [];
    let currentPage = 1;
    let perPage = window.innerWidth < 768 ? 8 : 5;
    let currentSlide = 0;
    let autoPlayTimer = null;
    let currentNewsId = null;

    // ========== 初始化函数 ==========
    function init() {
        loadNewsData();
        renderNewsPreview();
        renderNewsList();
        initSlider();
        initScrollAnimations();
        initStaggerAnimation();
        startSharePoll();
        loadNewsFromServer();

        // 监听管理后台数据变化
        window.addEventListener('storage', function(e) {
            if (e.key && (e.key.startsWith('jiaodao_') || e.key === 'jiaodao_sync_trigger')) {
                loadNewsData();
                renderNewsPreview();
                renderNewsList();
            }
        });

        window.addEventListener('storageChanged', function(e) {
            if (e.detail && e.detail.key && e.detail.key.startsWith('jiaodao_')) {
                loadNewsData();
                renderNewsPreview();
                renderNewsList();
            }
        });

        window.addEventListener('message', function(e) {
            if (e.data && (e.data.type === 'jiaodao_news_updated' || e.data.type === 'jiaodao_data_changed')) {
                loadNewsData();
                renderNewsPreview();
                renderNewsList();
            }
        });

        if (typeof BroadcastChannel !== 'undefined') {
            try {
                var _syncChannel = new BroadcastChannel('jiaodao_news_sync');
                _syncChannel.onmessage = function(e) {
                    if (e.data && e.data.type === 'news_updated') {
                        loadNewsData();
                        renderNewsPreview();
                        renderNewsList();
                    }
                };
            } catch (err) {}
        }

        var _lastSyncTime = localStorage.getItem('jiaodao_lastUpdate') || '';
        var _lastServerHash = '';
        setInterval(function() {
            var currentTime = localStorage.getItem('jiaodao_lastUpdate') || '';
            if (currentTime && currentTime !== _lastSyncTime) {
                _lastSyncTime = currentTime;
                loadNewsData();
                renderNewsPreview();
                renderNewsList();
                return;
            }
            fetch('/api/data/newsData', { method: 'GET' })
                .then(function(res) { return res.text(); })
                .then(function(text) {
                    var quickHash = text.length + '_' + text.slice(0, 50) + '_' + text.slice(-50);
                    if (_lastServerHash && quickHash !== _lastServerHash) {
                        _lastServerHash = quickHash;
                        loadNewsFromServer();
                    } else {
                        _lastServerHash = quickHash;
                    }
                })
                .catch(function() {});
        }, 1500);

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                loadNewsData();
                renderNewsPreview();
                renderNewsList();
            }
        });

        window.addEventListener('beforeunload', function() {
            if (typeof _syncChannel !== 'undefined' && _syncChannel) {
                try { _syncChannel.close(); } catch(e) {}
            }
        });
    }

    // ========== 新闻数据加载 ==========
    function loadNewsData() {
        const saved = localStorage.getItem('jiaodao_news');
        if (saved) {
            try {
                var parsed = JSON.parse(saved);
                if (parsed && Array.isArray(parsed) && parsed.length > 0) {
                    newsData = parsed.map(function(item) {
                        if (!item || typeof item !== 'object') return null;
                        return {
                            id: item.id || Date.now() + Math.random(),
                            title: item.title || '(无标题)',
                            author: item.author || '宣传部',
                            date: item.date || new Date().toISOString().split('T')[0],
                            desc: item.desc || '',
                            content: item.content || '',
                            status: item.status || 'published',
                            views: item.views || 0,
                            images: Array.isArray(item.images) ? item.images : []
                        };
                    }).filter(Boolean);
                    return;
                }
            } catch (e) {}
        }
        newsData = [...DEFAULT_NEWS];
    }

    function loadNewsFromServer() {
        fetch('/api/data/newsData')
            .then(function(res) { return res.json(); })
            .then(function(result) {
                if (!result.success || !result.data || !Array.isArray(result.data) || result.data.length === 0) return;
                var serverData = result.data;
                var needUpdate = false;
                var mergedData = [];
                for (var i = 0; i < serverData.length; i++) {
                    var sItem = serverData[i];
                    var localItem = null;
                    for (var j = 0; j < newsData.length; j++) {
                        if (String(newsData[j].id) === String(sItem.id)) { localItem = newsData[j]; break; }
                    }
                    if (localItem) {
                        var merged = Object.assign({}, sItem);
                        if ((!merged.content || merged.content === '' || merged.content.length < 5) &&
                            localItem.content && localItem.content.length > 5) {
                            merged.content = localItem.content;
                        }
                        if ((!merged.images || merged.images.length === 0) &&
                            localItem.images && localItem.images.length > 0) {
                            merged.images = localItem.images;
                        }
                        mergedData.push(merged);
                        if (JSON.stringify(merged) !== JSON.stringify(localItem)) needUpdate = true;
                    } else {
                        mergedData.push(sItem);
                        needUpdate = true;
                    }
                }
                if (mergedData.length !== newsData.length) needUpdate = true;
                if (needUpdate) {
                    newsData = mergedData;
                    saveNewsData(newsData);
                    renderNewsPreview();
                    renderNewsList();
                }
                if (_shareTargetId !== null && _sharePollTimer === null) {
                    tryOpenTargetNews();
                }
            })
            .catch(function(err) {});
    }

    // ========== 分享链接系统 ==========
    var _shareTargetId = null;
    _sharePollTimer = null;
    _sharePolledCount = 0;
    const _SHARE_MAX_POLL = 10;
    const _SHARE_POLL_INTERVAL = 200;

    function extractTargetNewsId() {
        var hash = location.hash;
        if (hash) {
            var m = hash.match(/^#news\\/(\\d+)/);
            if (m) return parseInt(m[1]);
        }
        var params = new URLSearchParams(location.search);
        if (params.get('news')) return parseInt(params.get('news'));
        return null;
    }

    function tryOpenTargetNews() {
        if (_shareTargetId === null) return false;
        if (!newsData || !Array.isArray(newsData) || newsData.length === 0) return false;
        for (var i = 0; i < newsData.length; i++) {
            if (String(newsData[i].id) === String(_shareTargetId)) {
                stopSharePoll();
                openNews(_shareTargetId);
                return true;
            }
        }
        return false;
    }

    function stopSharePoll() {
        if (_sharePollTimer) { clearInterval(_sharePollTimer); _sharePollTimer = null; }
    }

    function startSharePoll() {
        _shareTargetId = extractTargetNewsId();
        if (!_shareTargetId) return;
        _sharePolledCount = 0;
        if (tryOpenTargetNews()) return;
        _sharePollTimer = setInterval(function() {
            _sharePolledCount++;
            if (tryOpenTargetNews()) return;
            if (_sharePolledCount >= _SHARE_MAX_POLL) stopSharePoll();
        }, _SHARE_POLL_INTERVAL);
    }

    window.addEventListener('hashchange', function() {
        stopSharePoll();
        _sharePolledCount = 0;
        startSharePoll();
    });

    function saveNewsData(data) {
        try {
            if (!Array.isArray(data)) return;
            var validData = data.filter(function(item) { return item && typeof item === 'object' && item.id; });
            localStorage.setItem('jiaodao_news', JSON.stringify(validData));
        } catch (e) {}
    }

    function _esc(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function _decodeContent(raw) {
        if (!raw) return '';
        var content = String(raw);
        var entityTagCount = (content.match(/&lt;\\/?[a-zA-Z][^&]*?&gt;/g) || []).length;
        if (entityTagCount >= 2) {
            var tmp = document.createElement('div');
            tmp.innerHTML = content;
            content = tmp.textContent || tmp.innerText || content;
        }
        if ((content.indexOf('<article') === 0 || content.indexOf('<p') === 0 || content.indexOf('<span') === 0) &&
            (content.indexOf('&nbsp;') > -1 || content.indexOf('&lt;') > -1 || content.indexOf('&amp;') > -1)) {
            var tryDiv = document.createElement('div');
            tryDiv.innerHTML = content;
            var parsedText = tryDiv.textContent || '';
            if (parsedText.length > 20 && !parsedText.startsWith('<')) content = tryDiv.innerHTML;
        }
        var articleMatch = content.match(/<article[^>]*class="[^"]*4ever[^"]*"[^]*>([\\s\\S]*?)<\\/article>/i);
        if (articleMatch && articleMatch[1].trim()) content = articleMatch[1].trim();
        var sectionMatch = content.match(/<section[^>]*class="[^"]*4ever[^"]*"[^]*>([\\s\\S]*?)<\\/section>/i);
        if (sectionMatch && sectionMatch[1].trim()) content = sectionMatch[1].trim();
        content = content.replace(/<span\\s+data-type="text"\\s*>\\s*<\\/span>/gi, '');
        content = content.replace(/<p>\\s*<span\\s+data-type="text"\\s*>\\s*<\\/span>\\s*<\\/p>/gi, '');
        content = content.replace(/(&nbsp;){3,}/gi, '&nbsp;&nbsp;');
        content = content.replace(/(\\d)&nbsp;/gi, '&nbsp;');
        content = content.replace(/8nbsp;/gi, '&nbsp;');
        if (content.trim().length < 5) return raw;
        return content;
    }

    function renderNewsPreview() {
        const container = document.getElementById('newsPreviewList');
        if (!container) return;
        const sortedNews = [...newsData].sort((a, b) => new Date(b.date) - new Date(a.date));
        const previewNews = sortedNews.slice(0, 6);
        container.innerHTML = previewNews.map((news, index) => \`
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden card-hover cursor-pointer" data-news-id="\${news.id}" style="touch-action:manipulation;-webkit-tap-highlight-color:transparent;">
                <img src="/news-list-\${index + 1}.jpg" alt="新闻列表图\${index + 1}" class="w-full h-48 object-cover bg-gradient-to-br from-pkured/10 to-deepblue/10" loading="lazy" onerror="this.outerHTML='<div class=\\\\'w-full h-48 bg-gradient-to-br from-pkured/10 to-deepblue/10 flex items-center justify-center\\\\'><i class=\\\\'fas fa-newspaper text-5xl text-pkured/20\\\\'></i></div>'">
                <div class="p-6">
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
                        <span>\${_esc(news.date)}</span>
                        <span class="mx-2">·</span>
                        <span>\${_esc(news.views || 0)}次浏览</span>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2 line-clamp-2">\${_esc(news.title)}</h3>
                    <p class="text-gray-600 text-sm line-clamp-2">\${_esc(news.desc || '')}</p>
                </div>
            </div>
        \`).join('');
        container.onclick = function(e) {
            var item = e.target.closest('[data-news-id]');
            if (!item) return;
            if (container._lastClickTime && Date.now() - container._lastClickTime < 300) return;
            container._lastClickTime = Date.now();
            openNews(parseInt(item.getAttribute('data-news-id'), 10));
        };
    }

    function renderNewsList() {
        const container = document.getElementById('newsList');
        if (!container) return;
        const sortedNews = [...newsData].sort((a, b) => new Date(b.date) - new Date(a.date));
        const totalPages = Math.max(1, Math.ceil(sortedNews.length / perPage));
        if (typeof currentPage !== 'number' || isNaN(currentPage) || currentPage < 1) currentPage = 1;
        else if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * perPage;
        const end = start + perPage;
        const safeStart = Math.max(0, Math.min(start, sortedNews.length));
        const safeEnd = Math.max(safeStart, Math.min(end, sortedNews.length));
        const pageNews = sortedNews.slice(safeStart, safeEnd);
        container.innerHTML = pageNews.map((news) => \`
            <div class="news-item bg-gray-50 rounded-xl p-6" data-news-id="\${news.id}" style="cursor:pointer;touch-action:manipulation;-webkit-tap-highlight-color:transparent;">
                <div class="flex justify-between items-start mb-3">
                    <h3 class="text-xl font-bold text-gray-800 flex-1">\${_esc(news.title)}</h3>
                    <span class="text-sm text-gray-500 ml-4 whitespace-nowrap">\${_esc(news.date)}</span>
                </div>
                <p class="text-gray-600 mb-3">\${_esc(news.desc || '')}</p>
                <div class="flex items-center gap-4 text-sm text-gray-500">
                    <span>\${_esc(news.author || '宣传部')}</span>
                    <span>\${_esc(news.views || 0)}次浏览</span>
                </div>
            </div>
        \`).join('');
        container.onclick = function(e) {
            var item = e.target.closest('.news-item');
            if (!item) return;
            if (container._lastClickTime && Date.now() - container._lastClickTime < 300) return;
            container._lastClickTime = Date.now();
            var newsId = item.getAttribute('data-news-id');
            if (newsId) openNews(parseInt(newsId, 10));
        };
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        const container = document.getElementById('newsPagination');
        if (!container) return;
        if (totalPages <= 1) { container.innerHTML = ''; return; }
        let html = '<div class="flex justify-center items-center gap-2 flex-wrap pagination-container">';
        html += \`<button data-page="1" class="page-btn \${currentPage === 1 ? 'disabled' : ''}" \${currentPage === 1 ? 'disabled' : ''} title="首页"><i class="fas fa-angle-double-left"></i></button>\`;
        html += \`<button data-page="\${currentPage - 1}" class="page-btn \${currentPage === 1 ? 'disabled' : ''}" \${currentPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i>上一页</button>\`;
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                html += \`<button data-page="\${i}" class="page-btn \${i === currentPage ? 'active' : ''}">\${i}</button>\`;
            } else if (i === currentPage - 2 || i === currentPage + 2) {
                html += '<span class="px-2 text-gray-400">...</span>';
            }
        }
        html += \`<button data-page="\${currentPage + 1}" class="page-btn \${currentPage === totalPages ? 'disabled' : ''}" \${currentPage === totalPages ? 'disabled' : ''}>下一页<i class="fas fa-chevron-right ml-1"></i></button>\`;
        html += \`<button data-page="\${totalPages}" class="page-btn \${currentPage === totalPages ? 'disabled' : ''}" \${currentPage === totalPages ? 'disabled' : ''} title="末页"><i class="fas fa-angle-double-right"></i></button>\`;
        html += \`<span class="ml-4 text-sm text-gray-500 pagination-info">共 <strong class="text-pkured">\${totalPages}</strong> 页</span>\`;
        html += '</div>';
        container.innerHTML = html;
        container.onclick = function(e) {
            var btn = e.target.closest('.page-btn');
            if (!btn || btn.disabled || btn.classList.contains('disabled')) return;
            e.stopPropagation();
            var page = parseInt(btn.getAttribute('data-page'), 10);
            if (!isNaN(page)) goToPage(page);
        };
    }

    var _pageChanging = false;
    function goToPage(page) {
        if (_pageChanging) return;
        const sortedNews = [...newsData].sort((a, b) => new Date(b.date) - new Date(a.date));
        const totalPages = Math.ceil(sortedNews.length / perPage);
        if (page < 1 || page > totalPages) return;
        _pageChanging = true;
        currentPage = page;
        renderNewsList();
        setTimeout(function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            _pageChanging = false;
        }, 50);
    }

    function openNews(id) {
        const news = newsData.find(n => n.id === id);
        if (!news) return;
        currentNewsId = id;
        // 在多页面架构中，跳转到 news-detail.html
        window.location.href = 'news-detail.html#news/' + id;
    }

    function showPrevNews() {
        const sortedNews = [...newsData].sort((a, b) => new Date(b.date) - new Date(a.date));
        const currentIndex = sortedNews.findIndex(n => n.id == currentNewsId);
        if (currentIndex > 0) openNews(sortedNews[currentIndex - 1].id);
    }

    function showNextNews() {
        const sortedNews = [...newsData].sort((a, b) => new Date(b.date) - new Date(a.date));
        const currentIndex = sortedNews.findIndex(n => n.id == currentNewsId);
        if (currentIndex < sortedNews.length - 1) openNews(sortedNews[currentIndex + 1].id);
    }

    function updateNewsNavButtons() {
        const sortedNews = [...newsData].sort((a, b) => new Date(b.date) - new Date(a.date));
        const currentIndex = sortedNews.findIndex(n => n.id == currentNewsId);
        const prevBtn = document.getElementById('prev-news');
        const nextBtn = document.getElementById('next-news');
        if (prevBtn) prevBtn.style.visibility = currentIndex > 0 ? 'visible' : 'hidden';
        if (nextBtn) nextBtn.style.visibility = currentIndex < sortedNews.length - 1 ? 'visible' : 'hidden';
    }

    function getReviewerByDate(dateStr) {
        if (!dateStr) return '待定';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return '待定';
        if (d >= new Date('2025-11-18')) return '何俊杰、张钰';
        if (d >= new Date('2024-11-18') && d <= new Date('2025-11-17')) return '全德君、廖奕琪';
        if (d >= new Date('2023-11-16') && d <= new Date('2024-11-17')) return '尹尚琦、张玉洁';
        return '待定';
    }

    // ========== 新闻分享功能 ==========
    function getNewsShareData() {
        var news = null;
        for (var i = 0; i < newsData.length; i++) {
            if (newsData[i].id == currentNewsId) { news = newsData[i]; break; }
        }
        if (!news) return { title: '湖南涉外经济学院学生志愿教导大队', desc: '', url: location.href };
        return {
            title: news.title || '学生志愿教导大队通知公告',
            desc: (news.desc || '').replace(/<[^>]+>/g, '').substring(0, 100),
            url: location.origin + location.pathname + '#news/' + news.id
        };
    }

    function shareToWechat() {
        var data = getNewsShareData();
        alert('请使用微信「扫一扫」功能分享网页，或点击「复制链接」后在微信中发送给好友。\\n\\n链接：' + data.url);
    }

    function shareToWeibo() {
        var data = getNewsShareData();
        window.open('https://service.weibo.com/share/share.php?url=' + encodeURIComponent(data.url) + '&title=' + encodeURIComponent(data.title) + '&pic=', '_blank', 'width=600,height=500');
    }

    function shareToQQ() {
        var data = getNewsShareData();
        window.open('https://connect.qq.com/widget/shareqq/index.html?url=' + encodeURIComponent(data.url) + '&title=' + encodeURIComponent(data.title) + '&desc=' + encodeURIComponent(data.desc), '_blank', 'width=600,height=500');
    }

    function copyNewsLink() {
        var data = getNewsShareData();
        var text = data.title + '\\n' + data.desc + '\\n' + data.url;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(showCopySuccess).catch(() => fallbackCopy(text));
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { execCommand('copy'); showCopySuccess(); } catch(e) { alert('复制失败，请手动复制：\\n\\n' + text); }
        document.body.removeChild(ta);
    }

    function showCopySuccess() {
        var el = document.getElementById('copyLinkText');
        if (el) { el.textContent = '已复制!'; el.className = 'text-xs text-green-600 font-bold'; }
        setTimeout(() => { if (el) { el.textContent = '复制链接'; el.className = 'text-xs text-gray-600'; } }, 2000);
    }

    function shareNative() {
        var data = getNewsShareData();
        if (navigator.share) navigator.share({ title: data.title, text: data.desc, url: data.url }).catch(() => {});
        else copyNewsLink();
    }

    function backToNewsList() {
        window.location.href = 'news.html';
    }

    // ========== 轮播图功能 ==========
    function initSlider() {
        const slides = document.querySelectorAll('.slide');
        if (slides.length === 0) return;
        autoPlayTimer = setInterval(() => { goToSlide((currentSlide + 1) % slides.length); }, 5000);
    }

    function goToSlide(index) {
        const slides = document.querySelectorAll('.slide');
        const dots = document.querySelectorAll('.dot');
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        currentSlide = index;
        slides[index].classList.add('active');
        dots[index].classList.add('active');
        const img = slides[index].querySelector('img');
        if (img && img.getAttribute('data-src') && !img.src) img.src = img.getAttribute('data-src');
    }

    // ========== 领导详情（首页内嵌展示） ==========
    function showLeaderDetail(id) {
        const leader = leadersData[id];
        if (!leader) return;
        // 在多页面架构中，可以用模态框或跳转，这里保持简单处理
        alert(leader.name + '\\n' + leader.position + '\\n\\n' + leader.experience.replace(/<[^>]+>/g, ''));
    }

    // ========== 人员详情 ==========
    function showMemberDetail(name, position, img) {
        alert(name + '\\n' + position);
    }

    // ========== 滚动动画 ==========
    function initScrollAnimations() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('visible'); });
        }, { threshold: 0.1 });
        document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
    }

    // ========== 网格交错动画 ==========
    function initStaggerAnimation() {
        document.querySelectorAll('.stagger-grid').forEach(grid => {
            const items = grid.querySelectorAll(':scope > div');
            items.forEach((item, index) => {
                item.classList.add('stagger-item');
                item.style.animationDelay = \`\${index * 80}ms\`;
            });
        });
        const titles = document.querySelectorAll('.section-title');
        const titleObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('visible'); });
        }, { threshold: 0.5 });
        titles.forEach(title => titleObserver.observe(title));
    }

    // ========== 页面加载完成后初始化 ==========
    document.addEventListener('DOMContentLoaded', init);
`;

// ============================================================
// 导航栏模板生成函数
// ============================================================
function genNav(activePage) {
    const pages = [
        { id: 'home', label: '首页', href: 'index.html' },
        { id: 'about', label: '大队简介', href: 'about.html' },
        { id: 'org', label: '组织架构', href: 'org.html' },
        { id: 'news', label: '通知公告', href: 'news.html' },
    ];
    
    const hl = (id) => id === activePage ? 'text-pkured font-bold' : 'text-gray-700 hover:text-pkured';
    
    let pcNav = pages.map(p => 
        `<a href="${p.href}" class="nav-link ${hl(p.id)} font-medium py-2">${p.label}</a>`
    ).join('\n                    ');
    
    pcNav += `
                    <div class="relative group">
                        <a class="nav-link ${hl('current')} font-medium py-2">人员信息 <i class="fas fa-chevron-down text-xs ml-1"></i></a>
                        <div class="absolute top-full left-0 mt-2 w-48 bg-white rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300">
                            <a href="current.html" class="block px-4 py-3 text-gray-700 hover:bg-red-50 hover:text-pkured ${activePage === 'current' ? 'text-pkured font-bold' : ''}">第十五届干部</a>
                            <a href="history.html" class="block px-4 py-3 text-gray-700 hover:bg-red-50 hover:text-pkured ${activePage === 'history' ? 'text-pkured font-bold' : ''}">历任干部</a>
                        </div>
                    </div>
                    <div class="relative group">
                        <a class="nav-link ${hl('honor1')} font-medium py-2">荣誉墙 <i class="fas fa-chevron-down text-xs ml-1"></i></a>
                        <div class="absolute top-full left-0 mt-2 w-48 bg-white rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300">
                            <a href="honor1.html" class="block px-4 py-3 text-gray-700 hover:bg-red-50 hover:text-pkured ${activePage === 'honor1' ? 'text-pkured font-bold' : ''}">大队荣誉墙</a>
                            <a href="honor2.html" class="block px-4 py-3 text-gray-700 hover:bg-red-50 hover:text-pkured ${activePage === 'honor2' ? 'text-pkured font-bold' : ''}">个人荣誉墙</a>
                        </div>
                    </div>
                    <a href="join.html" class="nav-link ${hl('join')} font-medium py-2">招贤纳士</a>`;
    
    // Logo
    const logoHref = activePage === 'home' ? 'index.html' : 'index.html';
    const logoClass = activePage === 'home' ? '' : 'cursor-pointer';
    
    // Mobile nav
    let mobileNav = `<a href="index.html" class="block px-6 py-3 ${activePage === 'home' ? 'text-pkured font-bold bg-red-50' : 'text-gray-700 hover:bg-red-50 hover:text-pkured'} rounded-lg transition-all mobile-menu-item">首页</a>
            <a href="about.html" class="block px-6 py-3 ${activePage === 'about' ? 'text-pkured font-bold bg-red-50' : 'text-gray-700 hover:bg-red-50 hover:text-pkured'} rounded-lg transition-all mobile-menu-item">大队简介</a>
            <a href="org.html" class="block px-6 py-3 ${activePage === 'org' ? 'text-pkured font-bold bg-red-50' : 'text-gray-700 hover:bg-red-50 hover:text-pkured'} rounded-lg transition-all mobile-menu-item">组织架构</a>
            <a href="news.html" class="block px-6 py-3 ${activePage === 'news' ? 'text-pkured font-bold bg-red-50' : 'text-gray-700 hover:bg-red-50 hover:text-pkured'} rounded-lg transition-all mobile-menu-item">通知公告</a>

            <div class="pt-2 pb-1">
                <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">人员信息</p>
                <a href="current.html" class="block pl-8 pr-4 py-2.5 text-sm ${activePage === 'current' ? 'text-pkured font-bold bg-red-50' : 'text-gray-600 hover:bg-red-50 hover:text-pkured'} rounded-lg transition-all mobile-menu-item">第十五届干部</a>
                <a href="history.html" class="block pl-8 pr-4 py-2.5 text-sm ${activePage === 'history' ? 'text-pkured font-bold bg-red-50' : 'text-gray-600 hover:bg-red-50 hover:text-pkured'} rounded-lg transition-all mobile-menu-item">历任干部</a>
            </div>

            <div class="pt-2 pb-1">
                <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">荣誉墙</p>
                <a href="honor1.html" class="block pl-8 pr-4 py-2.5 text-sm ${activePage === 'honor1' ? 'text-pkured font-bold bg-red-50' : 'text-gray-600 hover:bg-red-50 hover:text-pkured'} rounded-lg transition-all mobile-menu-item">大队荣誉墙</a>
                <a href="honor2.html" class="block pl-8 pr-4 py-2.5 text-sm ${activePage === 'honor2' ? 'text-pkured font-bold bg-red-50' : 'text-gray-600 hover:bg-red-50 hover:text-pkured'} rounded-lg transition-all mobile-menu-item">个人荣誉墙</a>
            </div>

            <a href="join.html" class="block px-6 py-3 ${activePage === 'join' ? 'text-pkured font-bold bg-red-50' : 'text-gray-700 hover:bg-red-50 hover:text-pkured'} rounded-lg transition-all mobile-menu-item">招贤纳士</a>`;

    return { pcNav, mobileNav, logoHref };
}

// ============================================================
// Preload 配置
// ============================================================
const preloadConfig = {
    'index': {
        title: '学生志愿教导大队 - 官方网站',
        desc: '学生志愿教导大队官方网站，提供大队简介、通知公告、干部风采、荣誉墙、招贤纳士等信息。明理严军，自强不息。',
        preloads: [
            { href: '/logo.jpg', alt: 'Logo' },
            { href: '/slide5.jpg', alt: '轮播图' },
            { href: '/slide1.jpg', alt: '' },
            { href: '/slide2.jpg', alt: '' },
            { href: '/slide3.jpg', alt: '' },
            { href: '/slide6.jpg', alt: '' },
            { href: '/slide7.jpg', alt: '' },
            { href: '/leader1.jpg', alt: '' },
            { href: '/leader2.jpg', alt: '' },
            { href: '/org.jpg', alt: '' },
            { href: '/about-img1.jpg', alt: '' },
            { href: '/about-img2.jpg', alt: '' },
            { href: '/about-img3.jpg', alt: '' },
            { href: '/about-img4.jpg', alt: '' },
        ],
        forceLoadDiv: ['logo.jpg','slide5.jpg','slide1.jpg','slide2.jpg','slide3.jpg','slide6.jpg','slide7.jpg','leader1.jpg','leader2.jpg','org.jpg']
    },
    'about': {
        title: '大队简介 - 学生志愿教导大队',
        desc: '了解湖南涉外经济学院学生志愿教导大队的历史沿革、组织架构和主要职能。',
        preloads: [
            { href: '/logo.jpg', alt: 'Logo' },
            { href: '/about-img1.jpg', alt: '' },
            { href: '/about-img2.jpg', alt: '' },
            { href: '/about-img3.jpg', alt: '' },
            { href: '/about-img4.jpg', alt: '' },
        ],
        forceLoadDiv: ['logo.jpg','about-img1.jpg','about-img2.jpg','about-img3.jpg','about-img4.jpg']
    },
    'org': {
        title: '组织架构与职责 - 学生志愿教导大队',
        desc: '查看学生志愿教导大队的组织架构及各部门职责分工。',
        preloads: [
            { href: '/logo.jpg', alt: 'Logo' },
            { href: '/org.jpg', alt: '组织架构图' },
        ],
        forceLoadDiv: ['logo.jpg','org.jpg']
    },
    'current': {
        title: '第十五届主要负责干部 - 学生志愿教导大队',
        desc: '查看学生志愿教导大队第十五届干部成员信息。',
        preloads: [
            { href: '/logo.jpg', alt: 'Logo' },
            { href: '/member1.jpg', alt: '' }, { href: '/member2.jpg', alt: '' },
            { href: '/member3.jpg', alt: '' }, { href: '/member4.jpg', alt: '' },
            { href: '/office1.jpg', alt: '' }, { href: '/office2.jpg', alt: '' },
            { href: '/captain1.jpg', alt: '' }, { href: '/captain2.jpg', alt: '' },
            { href: '/captain3.jpg', alt: '' }, { href: '/captain4.jpg', alt: '' },
            { href: '/captain5.jpg', alt: '' }, { href: '/captain6.jpg', alt: '' },
            { href: '/jicheng1.jpg', alt: '' }, { href: '/jicheng2.jpg', alt: '' },
            { href: '/xuanchuan1.jpg', alt: '' }, { href: '/xuanchuan2.jpg', alt: '' },
            { href: '/houqin1.jpg', alt: '' }, { href: '/houqin2.jpg', alt: '' },
            { href: '/tuozhan1.jpg', alt: '' }, { href: '/tuozhan2.jpg', alt: '' },
            { href: '/squad1.jpg', alt: '' }, { href: '/squad2.jpg', alt: '' },
            { href: '/squad4.jpg', alt: '' }, { href: '/squad5.jpg', alt: '' },
            { href: '/squad6.jpg', alt: '' }, { href: '/squad7.jpg', alt: '' },
            { href: '/squad8.jpg', alt: '' }, { href: '/squad9.jpg', alt: '' },
        ],
        forceLoadDiv: ['logo.jpg']
    },
    'history': {
        title: '历任干部 - 学生志愿教导大队',
        desc: '查看学生志愿教导大队历届干部成员信息。',
        preloads: [
            { href: '/logo.jpg', alt: 'Logo' },
            { href: '/history_01.jpg', alt: '' }, { href: '/history_02.jpg', alt: '' },
            { href: '/history_03.jpg', alt: '' }, { href: '/history_04.jpg', alt: '' },
            { href: '/history_05.jpg', alt: '' }, { href: '/history_06.jpg', alt: '' },
            { href: '/history_07.jpg', alt: '' }, { href: '/history_08.jpg', alt: '' },
            { href: '/history_09.jpg', alt: '' }, { href: '/history_10.jpg', alt: '' },
        ],
        forceLoadDiv: ['logo.jpg']
    },
    'news': {
        title: '通知公告 - 学生志愿教导大队',
        desc: '学生志愿教导大队最新通知公告。',
        preloads: [
            { href: '/logo.jpg', alt: 'Logo' },
        ],
        forceLoadDiv: ['logo.jpg']
    },
    'news-detail': {
        title: '通知公告详情 - 学生志愿教导大队',
        desc: '学生志愿教导大队通知公告详情。',
        preloads: [
            { href: '/logo.jpg', alt: 'Logo' },
        ],
        forceLoadDiv: ['logo.jpg']
    },
    'honor1': {
        title: '大队荣誉墙 - 学生志愿教导大队',
        desc: '查看学生志愿教导大队获得的各项荣誉。',
        preloads: [
            { href: '/logo.jpg', alt: 'Logo' },
            { href: '/honor_team_1.jpg', alt: '' }, { href: '/honor_team_2.jpg', alt: '' },
            { href: '/honor_team_3.jpg', alt: '' }, { href: '/honor_team_4.jpg', alt: '' },
            { href: '/honor_team_5.jpg', alt: '' }, { href: '/honor_team_6.jpg', alt: '' },
        ],
        forceLoadDiv: ['logo.jpg']
    },
    'honor2': {
        title: '优秀队员 - 学生志愿教导大队',
        desc: '查看学生志愿教导大队优秀队员和教练员风采。',
        preloads: [
            { href: '/logo.jpg', alt: 'Logo' },
            ...Array.from({length:12}, (_,i)=>({href:`/excellent_${i+1}.jpg`,alt:''})),
            ...Array.from({length:4}, (_,i)=>({href:`/coach_${i+1}.jpg`,alt:''})),
        ],
        forceLoadDiv: ['logo.jpg']
    },
    'join': {
        title: '招贤纳士 - 学生志愿教导大队',
        desc: '加入湖南涉外经济学院学生志愿教导大队，成就更好的自己。',
        preloads: [
            { href: '/logo.jpg', alt: 'Logo' },
        ],
        forceLoadDiv: ['logo.jpg']
    }
};

// ============================================================
// 生成单个页面文件
// ============================================================
function genPage(pageKey, fileName, pageTitle, bodyContent, extraInit) {
    const cfg = preloadConfig[pageKey];
    const nav = genNav(pageKey);
    
    // 生成 preload 链接
    const preloadLinks = cfg.preloads.map(p => 
        `    <link rel="preload" href="${p.href}" as="image" fetchpriority="high">`
    ).join('\n');
    
    // 生成强制预加载 div
    const forceLoadImgs = cfg.forceLoadDiv.map(img =>
        `        <img src="/${img}" alt="${img}" onload="console.log('${img} loaded!')">`
    ).join('\n');

    // OG meta 根据 page 设置
    const ogTitle = cfg.title;
    const ogDesc = cfg.desc;

    const html = `<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>${cfg.title}</title>
    <meta name="description" content="${cfg.desc}">
    <meta name="keywords" content="涉外,学生志愿教导大队,涉外教导队,志愿教导大队,学生组织,社团,志愿服务,教导大队">
    <meta name="author" content="学生志愿教导大队">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta name="revisit-after" content="7 days">
    
    <link rel="canonical" href="https://www.jiaodaodadui.com.cn/${fileName}">
    
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/logo.jpg">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.jiaodaodadui.com.cn/${fileName}">
    <meta property="og:title" content="${ogTitle}">
    <meta property="og:description" content="${ogDesc}">
    <meta property="og:image" content="https://www.jiaodaodadui.com.cn/logo.jpg">
    <meta property="og:locale" content="zh_CN">
    <meta property="og:site_name" content="学生志愿教导大队">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://www.jiaodaodadui.com.cn/${fileName}">
    <meta name="twitter:title" content="${ogTitle}">
    <meta name="twitter:description" content="${ogDesc}">
    <meta name="twitter:image" content="https://www.jiaodaodadui.com.cn/logo.jpg">
    
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">

    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://www.jiaodaodadui.com.cn" crossorigin>
    
    <link rel="dns-prefetch" href="//www.jiaodaodadui.com.cn">
    <link rel="dns-prefetch" href="//cdn.tailwindcss.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    
${preloadLinks}
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "学生志愿教导大队",
        "alternateName": "教导大队",
        "url": "https://www.jiaodaodadui.com.cn/",
        "logo": "https://www.jiaodaodadui.com.cn/logo.jpg",
        "description": "学生志愿教导大队官方网站，明理严军，自强不息。"
    }
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'pkured': '#8B0000',
                        'pkured-dark': '#6B0000',
                        'pkured-light': '#A52A2A',
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
${rawCSS}
    </style>
</head>

<body class="bg-white">

    <!-- 强制同步预加载关键图片 -->
    <div style="position: absolute; width: 1px; height: 1px; overflow: hidden; opacity: 0;">
${forceLoadImgs}
    </div>

    <!-- 顶部通知栏 -->
    <div class="bg-pkured text-white py-2 text-sm hidden md:block">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-left">欢迎访问湖南涉外经济学院学生志愿教导大队官方网站</div>
        </div>
    </div>

    <!-- 导航头部 -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo区域 -->
                <a href="${nav.logoHref}" class="flex items-center gap-3">
                    <img src="/logo.jpg" alt="学生志愿教导大队 Logo"
                         class="w-10 h-10 md:w-14 md:h-14 rounded-full object-cover shadow-md border-2 border-white flex-shrink-0"
                         loading="eager"
                         fetchpriority="high"
                         decoding="sync"
                         onerror="this.style.display='none'">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-pkured font-serif tracking-wider">学生志愿教导大队</h1>
                    </div>
                </a>

                <!-- PC端导航 -->
                <nav class="hidden lg:flex items-center gap-8">
${nav.pcNav}
                </nav>

                <!-- 移动端汉堡菜单按钮 -->
                <button id="menuToggle" class="lg:hidden w-10 h-10 flex items-center justify-center rounded-full bg-pkured text-white hover:scale-110 active:scale-95 transition-transform duration-200" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars text-base"></i>
                </button>

            </div>
        </div>
    </header>

    <!-- 移动端侧边菜单 -->
    <div id="mobileMenuOverlay" class="fixed inset-0 bg-black/30 z-[60] hidden opacity-0 transition-opacity duration-400" onclick="toggleMobileMenu()"></div>

    <div id="mobileMenu" class="mobile-menu shadow-2xl overflow-y-auto">
        <div class="p-6 border-b border-gray-100">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-pkured font-serif">导航菜单</h2>
                <button onclick="toggleMobileMenu()" class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 transition-colors">
                    <i class="fas fa-times text-gray-600"></i>
                </button>
            </div>
        </div>
        
        <nav class="p-4 space-y-1">
${nav.mobileNav}
        </nav>
    </div>

    <!-- 页面主体内容 -->
    <main>
${bodyContent}
    </main>

    <!-- 页脚 -->
${cleanFooter}

    <!-- JavaScript 功能代码 -->
    <script>
${cleanJS}${extraInit || ''}
    </script>

    <!-- 百度站长平台自动推送代码 -->
    <script>
    (function(){
        var bp = document.createElement('script');
        var curProtocol = window.location.protocol.split(':')[0];
        if (curProtocol === 'https') {
            bp.src = 'https://zz.bdstatic.com/linksubmit/push.js';
        } else {
            bp.src = 'http://push.zhanzhang.baidu.com/push.js';
        }
        var s = document.getElementsByTagName("script")[0];
        s.parentNode.insertBefore(bp, s);
    })();

    // Service Worker 注册（轻量按需缓存版）
    (function() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).then(function() {
                console.log('✅ SW已注册（按需缓存模式）');
            }).catch(function(e) { console.log('⚠️ SW注册失败:', e.message); });
        }
    })();
    </script>

</body>
</html>`;

    return html;
}

// ============================================================
// 生成所有文件
// ============================================================

// 1. index.html (首页)
const indexBody = homeClean;
fs.writeFileSync(path.join(__dirname, 'index.html'), genPage('index', 'index.html', '首页', indexBody));
console.log('✅ index.html created');

// 2. about.html
fs.writeFileSync(path.join(__dirname, 'about.html'), genPage('about', 'about.html', '大队简介', aboutContent));
console.log('✅ about.html created');

// 3. org.html
fs.writeFileSync(path.join(__dirname, 'org.html'), genPage('org', 'org.html', '组织架构', orgContent));
console.log('✅ org.html created');

// 4. current.html
fs.writeFileSync(path.join(__dirname, 'current.html'), genPage('current', 'current.html', '第十五届干部', currentContent));
console.log('✅ current.html created');

// 5. history.html
fs.writeFileSync(path.join(__dirname, 'history.html'), genPage('history', 'history.html', '历任干部', historyContent));
console.log('✅ history.html created');

// 6. news.html
fs.writeFileSync(path.join(__dirname, 'news.html'), genPage('news', 'news.html', '通知公告', newsListContent));
console.log('✅ news.html created');

// 7. news-detail.html - 特殊处理：需要额外的初始化逻辑来从URL读取新闻ID并显示
const newsDetailExtraInit = `
    // ========== news-detail.html 专用：从URL hash加载通知公告详情 ==========
    (function() {
        function showNewsDetailFromHash() {
            var hash = location.hash;
            var match = hash.match(/^#news\\/(\\d+)/);
            if (!match) {
                // 没有新闻ID，显示提示或返回
                document.getElementById('detailTitle').textContent = '未指定新闻';
                document.getElementById('detailContent').innerHTML = '<p class="text-gray-400 text-center">请从<a href=\\"news.html\\" class=\\"text-pkured\\">新闻列表</a>选择要查看的新闻。</p>';
                return;
            }
            var newsId = parseInt(match[1], 10);
            
            // 先尝试从localStorage加载数据
            function tryShowNews() {
                var saved = localStorage.getItem('jiaodao_news');
                if (!saved) { setTimeout(tryShowNews, 100); return; }
                try {
                    var data = JSON.parse(saved);
                    if (Array.isArray(data) && data.length > 0) {
                        newsData = data.map(function(item) {
                            if (!item || typeof item !== 'object') return null;
                            return {
                                id: item.id || Date.now(),
                                title: item.title || '(无标题)',
                                author: item.author || '宣传部',
                                date: item.date || new Date().toISOString().split('T')[0],
                                desc: item.desc || '',
                                content: item.content || '',
                                views: item.views || 0,
                                images: Array.isArray(item.images) ? item.images : []
                            };
                        }).filter(Boolean);
                        
                        var news = newsData.find(n => n.id == newsId);
                        if (news) {
                            renderCurrentNewsDetail(news);
                        } else {
                            document.getElementById('detailTitle').textContent = '新闻不存在';
                            document.getElementById('detailContent').innerHTML = '<p class="text-gray-400 text-center">该新闻不存在或已被删除。</p>';
                        }
                    } else {
                        setTimeout(tryShowNews, 200);
                    }
                } catch(e) {
                    setTimeout(tryShowNews, 200);
                }
            }
            tryShowNews();
        }
        
        function renderCurrentNewsDetail(news) {
            currentNewsId = news.id;
            document.getElementById('detailTitle').textContent = news.title || '';
            document.getElementById('detailMeta').innerHTML = '<span>' + _esc(news.date) + '</span> | <span>' + _esc(news.author || '宣传部') + '</span> | <span>浏览 ' + _esc(news.views || 0) + ' 次</span>';
            var rawContent = news.content || '';
            var decodedContent = _decodeContent(rawContent);
            var detailEl = document.getElementById('detailContent');
            if (decodedContent && decodedContent.trim().length >= 5) {
                detailEl.innerHTML = decodedContent;
            } else if (rawContent && rawContent.trim().length >= 5) {
                detailEl.innerHTML = rawContent;
            } else {
                detailEl.innerHTML = '<p class="text-gray-400">正文暂无内容</p>';
            }
            
            // 处理图片显示
            const imagesContainer = document.getElementById('news-detail-images');
            if (news.images && Array.isArray(news.images) && news.images.length > 0) {
                imagesContainer.style.display = 'block';
                imagesContainer.innerHTML = '<div class="flex flex-col items-center gap-6 mt-8">' +
                    news.images.map(function(img) {
                        return '<div class="w-full max-w-2xl mx-auto text-center"><img src="' + _esc(img.src || img) + '" alt="新闻图片" class="w-full rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 max-h-[600px] object-contain" onerror="this.parentElement.style.display=\'none\'">' + (img.caption ? '<p class="text-sm text-gray-500 mt-3 text-center">' + _esc(img.caption) + '</p>' : '') + '</div>';
                    }).join('') + '</div>';
            } else {
                imagesContainer.style.display = 'none';
                imagesContainer.innerHTML = '';
            }
            
            var reviewer1El = document.getElementById('detailReviewer1');
            if (reviewer1El) reviewer1El.textContent = getReviewerByDate(news.date);
            updateNewsNavButtons();
        }
        
        // 页面加载后立即尝试显示新闻
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showNewsDetailFromHash);
        } else {
            showNewsDetailFromHash();
        }
    })();
`;

// news-detail 的 body 内容需要去掉 class="page-section"
let ndContent = newsDetailContent.replace('class="page-section" id="newsDetail"', 'id="newsDetail"');
fs.writeFileSync(path.join(__dirname, 'news-detail.html'), genPage('news-detail', 'news-detail.html', '通知公告详情', ndContent, newsDetailExtraInit));
console.log('✅ news-detail.html created');

// 8. honor1.html
fs.writeFileSync(path.join(__dirname, 'honor1.html'), genPage('honor1', 'honor1.html', '大队荣誉墙', honor1Content));
console.log('✅ honor1.html created');

// 9. honor2.html
fs.writeFileSync(path.join(__dirname, 'honor2.html'), genPage('honor2', 'honor2.html', '个人荣誉墙', honor2Content));
console.log('✅ honor2.html created');

// 10. join.html
fs.writeFileSync(path.join(__dirname, 'join.html'), genPage('join', 'join.html', '招贤纳士', joinContent));
console.log('✅ join.html created');

console.log('\\n🎉 All 10 files generated successfully!');
