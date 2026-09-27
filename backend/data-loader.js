// 数据加载适配器 - 支持本地存储和API两种模式
const USE_BACKEND_API = false; // 使用localStorage存储，保存时通过PHP接口同步到官网

// 存储键名
const STORAGE_KEYS = {
  NEWS: 'newsData',
  LEADERS: 'leadersData',
  ALBUM: 'albumData',
  CONFIG: 'configData'
};

// 默认数据
const DEFAULT_DATA = {
  news: [
    {
      id: 1,
      title: "教导大队第五届\"教导杯\"",
      desc: "5月20日下午，第十五届学生志愿教导大队召开常务委员会",
      date: "2026-05-20",
      author: "拓展部",
      views: "2560",
      content: "5月20日下午，第十五届学生志愿教导大队召开常务委员会",
      audit1: "宣传部",
      audit2: "何俊杰、张钰",
      audit3: "夏建平"
    },
    {
      id: 2,
      title: "第十五届换届仪式圆满举行",
      desc: "热烈庆祝第十五届学生志愿教导大队换届仪式",
      date: "2026-05-15",
      author: "宣传部",
      views: "1890",
      content: "热烈庆祝第十五届学生志愿教导大队换届仪式",
      audit1: "宣传部",
      audit2: "何俊杰、张钰",
      audit3: "夏建平"
    }
  ],
  leaders: {
    1: {
      name: "徐京都",
      post: "校长助理、学生工作处处长",
      desc: "徐京都，男，汉族，中共党员，现任湖南涉外经济学院校长助理、主管学生工作部（处）。曾任郑州工商学院书院负责人，信息工程学院党总支副书记，湖南涉外经济学院学工处副处长。",
      dept: "学生工作处（人民武装部）",
      photo: "leader1.jpg"
    },
    2: {
      name: "夏建平",
      post: "武装部副部长、教导大队指导老师",
      desc: "夏建平，男，汉族，中共党员，现任湖南涉外经济学院人民武装部副部长、军训领导小组办公室副主任、学生志愿教导大队指导老师。",
      dept: "学生工作处（人民武装部）",
      photo: "leader2.jpg"
    }
  },
  album: Array.from({ length: 20 }, (_, i) => ({ id: i + 1, image: `album${i + 1}.jpg`, desc: "精彩合集" })),
  config: {
    siteTitle: "湖南涉外经济学院学生志愿教导大队",
    sliderImages: ["slide1.jpg", "slide2.jpg", "slide3.jpg", "slide4.jpg", "slide5.jpg"]
  }
};

// 本地存储操作
class LocalStorageProvider {
  static load(key, defaultValue) {
    try {
      const data = localStorage.getItem(key);
      return data ? JSON.parse(data) : defaultValue;
    } catch (e) {
      return defaultValue;
    }
  }

  static save(key, data) {
    try {
      localStorage.setItem(key, JSON.stringify(data));
      return true;
    } catch (e) {
      console.error('保存失败:', e);
      return false;
    }
  }
}

// API提供者
class APIProvider {
  static async load(type) {
    if (!window.JiaoDaAPI) return DEFAULT_DATA[type];
    
    try {
      let result;
      switch (type) {
        case 'news':
          result = await window.JiaoDaAPI.NewsAPI.getAll();
          break;
        case 'leaders':
          result = await window.JiaoDaAPI.LeadersAPI.getAll();
          break;
        case 'album':
          result = await window.JiaoDaAPI.AlbumAPI.getAll();
          break;
        case 'config':
          result = await window.JiaoDaAPI.ConfigAPI.get();
          break;
      }
      
      if (result && result.success) {
        return result.data;
      }
      return DEFAULT_DATA[type];
    } catch (e) {
      console.error('API加载失败:', e);
      return DEFAULT_DATA[type];
    }
  }

  static async save(type, data) {
    if (!window.JiaoDaAPI) return false;
    
    try {
      let result;
      switch (type) {
        case 'news':
          // 新闻的增删改单独处理
          return true;
        case 'leaders':
          // 领导数据单独处理
          return true;
        case 'album':
          result = await window.JiaoDaAPI.AlbumAPI.update(data);
          break;
        case 'config':
          result = await window.JiaoDaAPI.ConfigAPI.update(data);
          break;
      }
      return result && result.success;
    } catch (e) {
      console.error('API保存失败:', e);
      return false;
    }
  }
}

// 统一数据接口
const DataManager = {
  // 加载数据
  async loadNews() {
    if (USE_BACKEND_API) {
      return await APIProvider.load('news');
    }
    return LocalStorageProvider.load(STORAGE_KEYS.NEWS, DEFAULT_DATA.news);
  },

  async loadLeaders() {
    if (USE_BACKEND_API) {
      return await APIProvider.load('leaders');
    }
    return DEFAULT_DATA.leaders; // 领导数据暂时保持静态
  },

  async loadAlbum() {
    if (USE_BACKEND_API) {
      return await APIProvider.load('album');
    }
    return LocalStorageProvider.load(STORAGE_KEYS.ALBUM, DEFAULT_DATA.album);
  },

  async loadConfig() {
    if (USE_BACKEND_API) {
      return await APIProvider.load('config');
    }
    return LocalStorageProvider.load(STORAGE_KEYS.CONFIG, DEFAULT_DATA.config);
  },

  // 保存数据
  async saveNews(data) {
    if (USE_BACKEND_API) {
      return await APIProvider.save('news', data);
    }
    return LocalStorageProvider.save(STORAGE_KEYS.NEWS, data);
  },

  async saveAlbum(data) {
    if (USE_BACKEND_API) {
      return await APIProvider.save('album', data);
    }
    return LocalStorageProvider.save(STORAGE_KEYS.ALBUM, data);
  },

  async saveConfig(data) {
    if (USE_BACKEND_API) {
      return await APIProvider.save('config', data);
    }
    return LocalStorageProvider.save(STORAGE_KEYS.CONFIG, data);
  },

  // 检查后端连接
  async checkBackendConnection() {
    if (!window.JiaoDaAPI) return false;
    try {
      const result = await window.JiaoDaAPI.checkHealth();
      return result && result.status === 'ok';
    } catch (e) {
      return false;
    }
  }
};

// 导出
window.DataManager = DataManager;
window.USE_BACKEND_API = USE_BACKEND_API;
