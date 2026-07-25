# Labelease - Vocabulary Memorization Website

## 🧭 Project Type
- Type: A) FULLSTACK_WEB (frontend + backend)

## 🧩 技术栈
- Frontend: HTML5, CSS3, Vanilla JavaScript (no frameworks)
- Backend: PHP 8.2 CLI with built-in server
- Web Server: Nginx (frontend) + PHP built-in server (backend)
- Database: MySQL 8.0
- Docker: Docker Compose for containerization

## 🚀 快速启动（Docker Gate）
1. 确保 Docker Desktop / Docker Engine 可用
2. 在根目录执行：`docker compose up --build`
3. 等待所有服务启动完成
4. 访问地址：
   - Web：http://localhost:3000
   - API：http://localhost:8000

## 👤 测试账号
无需登录即可使用所有功能

## ✅ 功能清单
- [x] F1：单词列表展示（Words 页面）- 显示所有词汇卡片
- [x] F2：添加新单词（带词义和例 API POST句表单）- /api/words
- [x] F3：学习模式（闪卡式学习）- 随机抽取学习，支持翻转
- [x] F4：进度追踪（统计和成就系统）- 展示学习进度百分比
- [x] F5：学习记录 - 每次答题记录单词、对错和时间
- [x] F6：错词本 - 答错单词自动入集，支持搜索、复习和移除
- [x] F7：进度统计增强 - 累计学习次数、正确率、最近7天趋势图

## 🔎 自测说明
### 成功路径
1. 打开浏览器访问 http://localhost:3000
2. 在 Words 页面查看预置的 10 个单词
3. 使用表单添加新单词，提交后立即显示在列表中
4. 进入 Study 页面，点击 Start Study Session 开始学习
5. 点击 Show Definition 翻转闪卡查看释义
6. 点击 Got It! 标记掌握，API 会更新数据库状态
7. 进入 Progress 查看学习进度统计和成就解锁

### 失败路径
1. 不填写单词或释义直接提交表单，会显示错误提示 Toast
2. 删除单词时有确认对话框防止误操作
3. Study 页面没有单词时显示提示信息

### 边界/异常
- API 请求失败显示红色错误 Toast
- Study 模式可选择 5/10/20 个单词
- 成就系统自动根据数据状态解锁

## 🧾 证据文件（截图/录屏）
| 文件 | 证明内容 |
|------|----------|
| evidence/01_boot.png | Docker Compose 启动成功日志 + 容器运行状态 |
| evidence/02_success.png | 单词列表页面成功加载，预置词汇显示 |
| evidence/03_failed.png | 表单验证失败时的错误提示 Toast |
| evidence/04_tree.png | 项目目录结构（docker-compose.yml, README, frontend, backend） |
| evidence/05_key_code.png | 关键后端代码片段（API 路由和处理逻辑） |

## 📁 项目结构
```
labelease/
├── docker-compose.yml      # Docker Compose 配置（3服务：frontend, backend, db）
├── README.md               # 项目说明
├── prompt.md               # 原始需求
├── backend/
│   ├── Dockerfile          # PHP 8.2 CLI 镜像
│   ├── index.php           # 主入口 + 数据库连接 + 路由分发
│   ├── init.sql            # 数据库初始化（words / study_records / wrong_words 表）
│   └── routes/
│       ├── words.php       # 单词 CRUD API (GET/POST/DELETE/PUT)
│       ├── progress.php    # 进度统计 API（含7天趋势）
│       ├── study.php       # 学习模式 API + 答题记录上报 + 错词复习抽取
│       └── wrong-words.php # 错词本 API（列表/搜索/移除）
├── frontend/
│   ├── index.html          # 单词列表页面
│   ├── study.html          # 学习页面（闪卡，支持错词复习模式）
│   ├── wrong-words.html    # 错词本页面（搜索/复习/移除）
│   ├── progress.html       # 进度页面（统计+趋势+成就）
│   ├── nginx.conf          # Nginx 配置（API 反向代理）
│   ├── css/style.css       # 完整样式（卡片、动画、响应式、趋势图）
│   └── js/
│       ├── app.js          # 公共逻辑（API 请求、提示、按钮状态、CRUD）
│       ├── study.js        # 学习页面逻辑（闪卡翻转、评分、记录上报）
│       ├── wrong-words.js  # 错词本页面逻辑
│       └── progress.js     # 进度页面逻辑（趋势图、成就解锁）
├── mysql/                  # MySQL 数据持久化卷
└── evidence/               # 证据截图
```

## 🔧 技术实现细节

### 后端 API 端点
| 端点 | 方法 | 功能 |
|------|------|------|
| /api/words | GET | 获取所有单词列表 |
| /api/words | POST | 添加新单词 |
| /api/words/{id} | PUT | 更新单词掌握状态 |
| /api/words/{id} | DELETE | 删除单词 |
| /api/progress | GET | 获取学习进度统计（含趋势数据） |
| /api/study | GET | 随机获取待学习单词 |
| /api/study/wrong-words | GET | 随机获取错词用于复习 |
| /api/study/records | POST | 提交一次答题记录 |
| /api/wrong-words | GET | 获取错词列表（支持搜索） |
| /api/wrong-words/{id} | DELETE | 从错词本移除单词 |

### 前端特性
- **响应式设计**：支持移动端和桌面端
- **交互动画**：闪卡翻转、Toast 提示、按钮 hover 效果
- **数据持久化**：MySQL 数据库持久化存储
- **错误处理**：完整的表单验证和 API 错误提示
