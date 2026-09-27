// 🎯 前端快速集成脚本

window.JiaoDaBackend = {
  /**
   * 从后端加载新闻
   */
  async loadNewsFromBackend() {
    return fetch('/api/news')
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          return data.data;
        }
        throw new Error('加载失败');
      });
  },
  
  /**
   * 保存新闻到后端
   */
  async saveNewsToBackend(newsItem) {
    return fetch('/api/news', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newsItem)
    }).then(res => res.json());
  },

  /**
   * 检查后端健康
   */
  async checkHealth() {
    return fetch('/api/health')
      .then(res => res.json())
      .catch(() => ({ status: 'error' }));
  }
};

// 自动检查后端是否可用
(async function() {
  try {
    const health = await window.JiaoDaBackend.checkHealth();
    console.log('🔧 后端状态:', health);
  } catch (e) {
    console.log('⚠️  后端不可用，使用本地存储');
  }
})();
