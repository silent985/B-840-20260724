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
- [x] F5：学习记录 - 每次答题记录单词、结果和学习时间 - /api/records
- [x] F6：错词复习（Wrong Words 页面）- 答错自动入集，可筛选、复习、移除 - /api/wrong-words
- [x] F7：进度增强 - 累计学习次数、正确率及最近 7 天学习趋势图

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
│   ├── init.sql            # 数据库初始化（单词表 + 学习记录表 + 错词集表）
│   └── routes/
│       ├── words.php       # 单词 CRUD API (GET/POST/DELETE/PUT)
│       ├── progress.php    # 进度统计 API（累计次数、正确率、7 天趋势）
│       ├── study.php       # 学习模式 API (随机抽取)
│       ├── records.php     # 答题记录 API（记录结果并维护错词集）
│       └── wrong_words.php # 错词集 API（列表 / 移除）
├── frontend/
│   ├── index.html          # 单词列表页面
│   ├── study.html          # 学习页面（闪卡）
│   ├── wrong-words.html    # 错词复习页面
│   ├── progress.html       # 进度页面（统计+趋势+成就）
│   ├── nginx.conf          # Nginx 配置（API 反向代理）
│   ├── css/style.css       # 完整样式（卡片、动画、趋势图、响应式）
│   └── js/
│       ├── app.js          # 公共逻辑（API 请求、Toast、CRUD）
│       ├── study.js        # 学习页面逻辑（闪卡翻转、答题记录）
│       ├── wrong-words.js  # 错词页面逻辑（筛选、复习、移除）
│       └── progress.js     # 进度页面逻辑（统计、趋势图、成就解锁）
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
| /api/progress | GET | 获取学习进度统计（含累计次数、正确率、7 天趋势） |
| /api/study | GET | 随机获取待学习单词 |
| /api/records | POST | 记录一次答题（单词、结果、时间），原子更新掌握状态与错词集；支持 client_token 幂等 |
| /api/wrong-words | GET | 获取错词集列表 |
| /api/wrong-words/{word_id} | DELETE | 从错词集移除单词 |

### 数据完整性与健壮性
- **原子事务**：`/api/records` 在单个事务内完成「保存答题记录 → 更新掌握状态 → 维护错词集」，任一步失败整体回滚。
- **幂等**：`client_token` 必填并与 `word_id`、`is_correct` 绑定；同一令牌相同负载重放已有结果（200），不同负载返回 409 并回传服务端权威结果。前端以服务端返回的 `is_correct` 计分，重试改答案也不会造成页面与数据库不一致。
- **历史留存**：删除单词时 `study_records.word_id` 置空（`ON DELETE SET NULL`）并保留 `word_snapshot`，累计次数、正确率与趋势不受影响（进度页在单词数为 0 但有历史记录时仍展示）；错词集依赖释义供复习，故随单词删除而级联清除。
- **幂等删除**：重复移除同一错词返回 200 成功（`removed` 标识本次是否真正删除），非报错。
- **统一错误**：所有路由的数据库操作均返回 JSON 错误与合理状态码，`progress` 对非 GET 请求返回 405，并有全局异常兜底。

### 数据库升级（已有持久化卷）
初次引入本迭代表结构时，若 `mysql_data` 卷已存在旧数据，`init.sql` 不会重跑。使用可重复执行、不丢数据的迁移脚本升级：

```bash
docker compose up -d --build
docker compose exec backend php migrate.php
```

脚本会按 `information_schema` 探测后再执行，安全幂等且可在任意步骤中断后重跑：创建缺失的 `study_records` / `wrong_words`，将旧版 `study_records` 升级为新结构（每次运行都回填空的 `word_snapshot`、`client_token` 回填后设为 `NOT NULL UNIQUE`、`word_id` 改可空、外键改 `ON DELETE SET NULL`），并校验/修复两张表已有字段的类型、空值约束、唯一索引与外键规则。结束前会做数据完整性与结构一致性校验，确保迁移库与 `init.sql` 创建的新库结构完全一致；校验不通过时以非零码退出。全新环境无需迁移，`init.sql` 已是目标结构。

> 若确实需要重置数据，可执行 `docker compose down -v` 清空 `mysql_data` 卷后重新 `docker compose up --build`（会清除现有数据）。

### 前端特性
- **响应式设计**：支持移动端和桌面端
- **交互动画**：闪卡翻转、Toast 提示、按钮 hover 效果
- **数据持久化**：MySQL 数据库持久化存储
- **错误处理**：完整的表单验证和 API 错误提示
