// API基础配置 - 部署时修改为实际地址
const API_BASE_URL = '/api';

// 通用API请求函数
async function apiRequest(url, options = {}) {
  try {
    const response = await fetch(url, {
      headers: {
        'Content-Type': 'application/json',
        ...options.headers
      },
      ...options
    });
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('API请求失败:', error);
    return { success: false, message: '网络请求失败' };
  }
}

// ==================== 新闻API ====================
const NewsAPI = {
  async getAll() {
    return await apiRequest(`${API_BASE_URL}/news`);
  },
  
  async getById(id) {
    return await apiRequest(`${API_BASE_URL}/news/${id}`);
  },
  
  async create(newsItem) {
    return await apiRequest(`${API_BASE_URL}/news`, {
      method: 'POST',
      body: JSON.stringify(newsItem)
    });
  },
  
  async update(id, newsItem) {
    return await apiRequest(`${API_BASE_URL}/news/${id}`, {
      method: 'PUT',
      body: JSON.stringify(newsItem)
    });
  },
  
  async delete(id) {
    return await apiRequest(`${API_BASE_URL}/news/${id}`, {
      method: 'DELETE'
    });
  }
};

// ==================== 领导API ====================
const LeadersAPI = {
  async getAll() {
    return await apiRequest(`${API_BASE_URL}/leaders`);
  },
  
  async update(id, leaderData) {
    return await apiRequest(`${API_BASE_URL}/leaders/${id}`, {
      method: 'PUT',
      body: JSON.stringify(leaderData)
    });
  }
};

// ==================== 相册API ====================
const AlbumAPI = {
  async getAll() {
    return await apiRequest(`${API_BASE_URL}/album`);
  },
  
  async update(albumData) {
    return await apiRequest(`${API_BASE_URL}/album`, {
      method: 'PUT',
      body: JSON.stringify(albumData)
    });
  }
};

// ==================== 配置API ====================
const ConfigAPI = {
  async get() {
    return await apiRequest(`${API_BASE_URL}/config`);
  },
  
  async update(config) {
    return await apiRequest(`${API_BASE_URL}/config`, {
      method: 'PUT',
      body: JSON.stringify(config)
    });
  }
};

// ==================== 健康检查 ====================
async function checkHealth() {
  return await apiRequest(`${API_BASE_URL}/health`);
}

// 导出所有API
window.JiaoDaAPI = {
  NewsAPI,
  LeadersAPI,
  AlbumAPI,
  ConfigAPI,
  checkHealth
};
