# 🎉 教导大队官网后端

## 📋 项目介绍

这是教导大队官网的轻量级后端，支持：

- ✅ RESTful API
- ✅ JSON文件数据存储
- ✅ 钉钉审批自动发布新闻
- ✅ PM2进程管理

---

## 📁 文件说明

| 文件 | 说明 |
|-----|------|
| server.js | 后端服务主文件 |
| package.json | 项目依赖配置 |
| api.js | 前端API库 |
| data-loader.js | 数据加载适配器 |
| .env.example | 环境变量示例 |
| 宝塔部署指南.md | 宝塔面板部署指南 |
| 钉钉配置指南.md | 钉钉配置详细指南 |
| 前端适配代码.md | 前端适配说明 |
| 快速部署清单.md | 快速部署检查清单 |

---

## 🚀 快速开始

### 本地开发

```bash
# 1. 安装依赖
npm install

# 2. 创建.env
cp .env.example .env

# 3. 启动服务
npm start
```

### 服务器部署

详见「宝塔部署指南.md」

---

## 📊 API接口

### 新闻
| 方法 | 路径 | 说明 |
|-----|------|-----|
| GET | /api/news | 获取所有新闻 |
| GET | /api/news/:id | 获取单条新闻 |
| POST | /api/news | 创建新闻 |
| PUT | /api/news/:id | 更新新闻 |
| DELETE | /api/news/:id | 删除新闻 |
| GET | /api/leaders | 获取领导信息 |
| GET | /api/album | 获取相册 |
| GET | /api/config | 获取配置 |
| GET | /api/health | 健康检查 |

### 钉钉
| 方法 | 路径 | 说明 |
|-----|------|
| GET | /dingtalk/callback | 钉钉回调 |
| POST | /dingtalk/callback | 钉钉事件 |

---

## 🔔 钉钉集成

1. 在钉钉开放平台创建应用
2. 设计审批表单
3. 配置事件订阅
4. 配置环境变量
5. 部署并测试

详细步骤参考「钉钉配置指南.md」

---

## 🛠️ 常见问题

### 服务无法启动

```bash
# 检查端口
netstat -tulpn | grep 3000

# 检查日志
pm2 logs jiaoda-backend
```

### 钉钉回调失败

- 检查回调地址
- 检查Token和AES Key
- 检查PM2日志

---

## 📝 License

MIT
