/**
 * 图片缓存服务 (sw.js) - 轻量版 v5
 *
 * 设计原则：
 * 1. 不在安装时批量预缓存（避免HTTP/2协议错误）
 * 2. 只缓存用户实际浏览过的图片（按需缓存）
 * 3. 缓存策略：Cache-First（有缓存直接用，没有才请求网络）
 * 4. 绝不缓存错误响应
 *
 * 部署位置：上传到网站根目录，与 index.html 同级
 */

var CACHE_VERSION = 'v5';
var CACHE_NAME = 'jiaodao-cache-' + CACHE_VERSION;

// 安装：不做任何预缓存，立即激活
self.addEventListener('install', function(event) {
    self.skipWaiting();
});

// 激活：清理旧版本缓存
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(names) {
            return Promise.all(
                names.filter(function(n) {
                    return n.startsWith('jiaodao-cache-') && n !== CACHE_NAME;
                }).map(function(n) { return caches.delete(n); })
            );
        }).then(function() { return self.clients.claim(); })
    );
});

// 拦截图片请求：Cache-First 策略
self.addEventListener('fetch', function(event) {
    var req = event.request;

    // 只处理 GET 图片请求
    if (req.method !== 'GET') return;

    var url;
    try { url = new URL(req.url); } catch(e) { return; }

    if (!/\.(jpg|jpeg|png|gif|webp|svg|ico|bmp)(\?|$)/i.test(url.pathname)) return;

    event.respondWith(
        caches.match(req).then(function(cached) {
            if (cached) return cached; // 命中缓存，直接返回

            // 未命中：请求网络
            return fetch(req).then(function(res) {
                // 只缓存成功的响应
                if (res && res.ok) {
                    var clone = res.clone();
                    caches.open(CACHE_NAME).then(function(cache) {
                        cache.put(req, clone);
                    });
                }
                return res;
            });
        })
    );
});
