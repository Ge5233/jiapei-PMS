# 佳培产品管理系统（PMS）— 项目记录

> 🚨 **每次新对话/新任务开始，第一件事读这个文件**。后续维护交接、看一遍就知道整个项目状态。

---

## 一、项目是什么

**佳培产品管理系统（PMS）** — 公司内部产品资料 + 价格管理后台

| 项 | 值 |
|---|---|
| 项目代号 | 佳培产品管理网页应用 / PMS |
| 用户规模 | 3-4 人（内部使用） |
| 当前版本 | **v4.0**（2026-08-08 报价系统上线） |
| 技术栈 | PHP 8.2 + MySQL 5.7 + 原生 PHP（无框架）+ Tailwind CDN + Alpine.js CDN |
| 部署位置 | 腾讯云轻量服务器 101.43.8.128 + 宝塔面板 |
| 访问地址 | http://101.43.8.128:8081 |
| 部署路径 | /www/wwwroot/pms |
| 端口 | 8081（绕过 80，避开扣子应用）|

## 二、当前服务器状态（2026-08-07 验证）

| 检查项 | 结果 | 验证方式 |
|---|---|---|
| 网站在跑 | ✅ 运行中 | 宝塔「网站」列表 |
| nginx 监听 8081 | ✅ 正常 | 显示登录页 |
| Git 关联 | ✅ `Ge5233/jiapei-PMS` main | `git remote -v` |
| 应用代码 | ✅ v3.9 | 已部署（commit `206cf61`） |
| `.env` 密码 | ✅ 保留 | `.gitignore` 排除 |
| `uploads/` 产品图片 | ✅ 保留（3 张） | `.gitignore` 排除 |
| 新表 `self_products` | ✅ 已建 | v3.9 新增 |
| 新表 `self_product_items` | ✅ 已建 | BOM 物料清单 |
| SSH 密钥 | ✅ 可用 | 从本地可直接进服务器 |

### v3.8 关键验证

服务器 `product_edit.php`（响应 35862 字节）含 v3.8 关键改动源码：
```js
addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
```
对 5 个价格字段（进价/售价/折扣/毛利率/最低实际毛利率）都加了此 handler。本地 v3.8 源码 product_edit.php 第 626-642 行同样有 5 处，完全匹配。

`assets/app.js` 和 `assets/app.css` 与本地 v3.8 SHA256 完全一致（`28b70c640d3da345`、`90be288a77d9f3c9`）。

## 三、部署历史

| 时间 | 事件 | 来源 |
|---|---|---|
| 之前 | 扣子开发并部署到服务器 | 不详 |
| 2026-07-31 | 安全加固时「PMS旧系统清理」：install.php 重命名为 .bak | IOT平台/PROJECT.md 第 635 行 |
| 2026-08-07 | 小落接手，确认服务器为 v3.8；改为 GitHub 部署 | 本次 |
| 2026-08-07 | v3.9 自产产品管理模块上线 — BOM物料 + 成本自动计算 + 产品主图 | 本次 |

## 四、部署流程（2026-08-07 改造为 GitHub 部署）

### 部署历史

| 时间 | 事件 |
|---|---|
| 之前 | 扣子沙箱开发 → 导出 tar.gz → 手工上传宝塔 `/www/wwwroot/pms` |
| 2026-07-31 | 安全加固时「PMS旧系统清理」：install.php 重命名为 .bak |
| **2026-08-07** | **改造为 GitHub 部署 + v3.9 自产产品**（以下记录） |
| **2026-08-08** | **v4.0 报价系统上线 + 价格区重写** |

### GitHub 部署配置

| 项 | 值 |
|---|---|
| GitHub 仓库 | `Ge5233/jiapei-PMS` |
| 仓库 URL | https://github.com/Ge5233/jiapei-PMS |
| Clone 地址 | `https://github.com/Ge5233/jiapei-PMS.git`（token：`user:ghp_...`） |
| 分支 | `main` |
| 服务器路径 | `/www/wwwroot/pms` |
| `.env` | 不推 GitHub（在 `.gitignore` 中） |
| `uploads/` | 不推 GitHub（product image files 太大且敏感） |
| `installed.lock` | 不推 GitHub（运行时标志） |

### 后续开发部署流程

```
本地改代码 → git commit → git push origin main
                    ↓
         SSH 进服务器 git pull + 跑 SQL（小落直接操作）
                    ↓
              即时生效（PHP 无需构建）
```

部署命令（小落执行）：
```bash
# 1. 推送
cd C:\Users\MR NO.1\WorkBuddy\佳培产品管理网页应用\src && git add -A && git commit -m "描述" && git push

# 2. SSH 进服务器拉代码
ssh root@101.43.8.128 "cd /www/wwwroot/pms && git pull"

# 3. 如有 SQL 变更
ssh root@101.43.8.128 "mysql -u pms_user -p'密码' pms_db < /www/wwwroot/pms/sql/upgrade_vX_Y.sql"
```

### 本次改造操作记录（2026-08-07）

1. GitHub API 创建空仓库 `Ge5233/jiapei-PMS`（commit `3f43d38a`）
2. `/www/wwwroot/jiapei-pms-tmp` 克隆 + 复制源码 + 配置 `.gitignore`
3. 服务器推送 v3.8 baseline（commit `99e2812`）
4. `/www/wwwroot/pms` 替换为 git clone 版本
5. 恢复 `.env`（数据库密码）+ `uploads/`（产品图片）+ `installed.lock`
6. 验证网站正常（HTTP 200 / 登录页正常）

## 五、版本特性

### v3.8 及之前

- **v3.8**：(2026-06-29) 产品编辑页价格输入框按回车不再触发表单提交
- **v3.7**：(2026-06-29) 父分类独立编号 parent_sort_id + 子分类独立编号 sub_id；SKU 格式优化；毛利率双向计算
- **v3.4**：(2026-06-29) 分类页显示 ID；删除分类提醒 SKU
- **v3.3**：(2026-06-29) SKU 自动生成 + 批量生成
- **v3.2**：(2026-06-23) 分类 combobox 空状态修复；供应商 combobox
- **v3.1**：(2026-06-23) 产品编辑页交互优化
- **v3.0**：(2026-06-23) 供应商管理模块；折扣限制；实时毛利率计算；分类树形搜索

### v4.0 (2026-08-08) — 报价系统

**新增：**
- 产品定价规则：每个产品加「指导价系数」+「最低售价」
  - 外采产品默认系数 1.10，外协加工（一级分类ID=43）默认 1.35
  - 自产产品默认系数 1.60
- 整单报价模块：报价单列表 + 新建/编辑 + 打印/导出
- 报价单支持三种来源：外采产品 / 自产产品 / 临时项（含施工安装费）
- 自动计算：单价 × 折扣 = 行小计 → 汇总 → 税费 → 合计
- 客户版打印页（隐藏成本，浏览器 Ctrl+P 直接打印）
- 侧边栏「报价计算器」→「报价管理」

**文件变更：** 新 7 文件 + 改 5 文件
**数据库：** products/self_products 加 2 字段；新建 quotes + quote_items 表
**GitHub：** commit `5e997c6`

**新增：**
- 侧边栏「产品管理」→「外采产品管理」+ 新菜单「自产产品」
- 自产产品列表页（搜索/筛选/分页/删除）
- 自产产品编辑页（基本信息 + 产品主图上传 + 成本定价 + BOM 物料清单）
- 成本自动计算：材料成本（BOM 汇总）+ 人工成本 + 制造费用 = 总成本
- 毛利率实时计算：(售价 - 总成本) / 售价 × 100%
- BOM 物料支持两种模式：
  - 模式 A：关联外采产品（单价**动态取最新进价**，进价涨了成本自动跟）
  - 模式 B：临时物料（手动填名称+单价，用于不在外采清单里的物料）
- 产品主图上传（jpg/png，≤2MB）

**文件变更：**
- 新增 6 文件：`self_products.php`、`self_product_edit.php`、`api/self_product_save.php`、`api/self_product_delete.php`、`includes/models/SelfProduct.php`、`sql/upgrade_v3_9.sql`
- 修改 2 文件：`includes/views/header.php`（菜单改名+加项）、`dashboard.php`（统计卡片）

**数据库：**
- 新增 `self_products` 表（自产产品）
- 新增 `self_product_items` 表（BOM 物料清单）

**GitHub 提交：**
- v3.8 baseline：commit `99e2812`
- v3.9 发布：commit `206cf61`

详见 `SPEC.md`。

## 六、目录结构（精简版）

```
C:\Users\MR NO.1\WorkBuddy\佳培产品管理网页应用\
├── PROJECT.md               ← 本文件（项目中央记忆）
├── SPEC.md                  ← v3.9 自产产品模块方案
├── pms-v3.8-handover.tar.gz ← 原始交付包
├── src/                     ← 🔥 GitHub 同步后的主开发目录（git 仓库）
│   ├── README.md / AGENTS.md / DESIGN.md / HANDOVER.md
│   ├── dashboard.php / products.php / self_products.php / self_product_edit.php
│   ├── categories.php / supplier.php / users.php / quote.php / profile.php / export.php
│   ├── api/                 ← AJAX 接口（含 self_product_save/delete）
│   ├── includes/            ← bootstrap / db / auth / functions / models（含 SelfProduct.php）
│   ├── assets/              ← app.css + app.js
│   ├── sql/                 ← schema.sql + 升级脚本（含 upgrade_v3_9.sql）
│   └── uploads/             ← 用户上传文件（gitignore 排除）
├── extracted/               ← 旧工作目录（过渡期保留，后续可删）
└── .tmp/                    ← 临时文件（gitignore 排除）
```

## 七、数据库

- 库名：`pms_db`
- 用户：`pms_user`（密码：`82adHCP2zeAGimbF`）
- 10 张表：users / categories / products / suppliers / product_files / price_history / operation_logs / self_products / self_product_items / **quotes** / **quote_items**
- v4.0 升级：`sql/upgrade_v4_0.sql`

## 八、关键文档（`extracted/` 下）

| 文件 | 作用 |
|---|---|
| `README.md` | 宝塔部署文档（小白版），含 v3.0~v3.8 更新记录 |
| `AGENTS.md` | 项目规范（目录、6 张表、开发规范、命名、安全、命令） |
| `DESIGN.md` | 设计语言（色彩/字体/间距/组件/交互/禁忌） |
| `HANDOVER.md` | 交接说明（沙箱流程、文件清单、运行约束） |
| `UPGRADE_V3.md` | v3.0 升级脚本说明 |
| `.coze` | 扣子沙箱启动配置（`php-8.3` + `mysql-5.7`） |
| `.env.example` | 环境变量模板 |

## 九、安全要点（开发遵守）

- 密码哈希用 `password_hash()` + `PASSWORD_DEFAULT`
- Session 有效期 2 小时
- CSRF token 每次写操作校验
- 文件上传限制：图片 5MB / PDF 10MB / Word/Excel 10MB
- 禁止上传可执行文件
- `includes/` 通过 `.htaccess` 禁止 HTTP 访问
- `uploads/` 通过 `.htaccess` 禁止 PHP 执行
- 所有用户输入必须 `h()` 转义后再输出
- 所有写操作必须写 `operation_logs`

## 十、待办与下一步

### 已完成
- [x] v3.9 自产产品管理模块（列表+编辑+BOM+成本+主图）
- [x] 侧边栏改名「产品管理」→「外采产品管理」
- [x] GitHub 部署流程建立（`Ge5233/jiapei-PMS`）
- [x] SSH 密钥可直连服务器（免密部署）

### 待确认
- [x] 阔已登录试用自产产品功能，2026-08-08 确认方向
- [x] 2026-08-08 阔也定价讨论：确认 v4.0 报价系统蓝图
- [ ] 后续要不要给自产产品加 SKU 自动生成

### v4.0 报价系统待开发
- [ ] 外采产品批量设置默认指导价系数 + 最低售价
- [ ] 整单报价模块（多报价单 + 系统选品 + 手动临时项）
- [ ] 报价单导出/打印（客户版，隐藏成本列）
- [ ] 自产产品定价助手（录入市场价 → 自动给出建议售价区间）

### 距离下一次的「容易忘记的事」
- ✅ 本地主开发目录是 `src/`
- ✅ GitHub 已配代理：`git config --global http.proxy http://127.0.0.1:7890`
- 🚨 **给阔也的服务器拉取命令：**
  ```bash
  cd /www/wwwroot/pms && git checkout -- . && for i in 1 2 3 4 5; do echo "拉取第${i}次..." && git pull && echo "✅ 拉取成功！" && break || sleep 15; done
  ```
- ✅ Gitee 服务器拉取地址：`https://jiapeicode:8f0139677f817e5da92a2bd63d087244@gitee.com/jiapeicode/jiapei-PMS.git`
- ✅ 本地 Git 双推：`origin push` → GitHub + Gitee 同时
- ✅ 定价规则（v4.2 毛利率模式）：指导售价 = 综合进价 / (1 - 指导毛利率%)；最低售价 = 综合进价 / (1 - 最低毛利率%)；最高折扣 = (1-指导毛利率) / (1-最低毛利率) × 100%
- ✅ 默认毛利率：外协(分类43) 指导40% 最低25%，其它 指导30% 最低15%，自产 指导30% 最低15%
- ✅ 旧系数列 (guide_price_coefficient/min_price_coefficient) 保留但不再使用，新列 (guide_margin_rate/min_margin_rate) 替代

## 十一、版本历史

### v4.3 — 大型系统 BOM + 可搜索下拉框 + Gitee 部署 (2026-08-09)
- 新建大型系统页面：四级表（项目→模块→主材→紧固件），三级物料兼容三来源（外采/自产/临时）
- 双视图：按模块（报价用）、物料汇总（采购用）
- 查看/编辑模式切换，防误操作
- 全站产品选择换可搜索输入框：大型系统 / BOM / 报价单 三页面全线替换
- 下拉显示 SKU + 名称 + 规格，选中自动填规格+单位+单价
- 搜索结果按 SKU 排序
- Gitee 仓库部署：本地 push 双推 GitHub+Gitee，服务器从 Gitee 秒拉（不走墙）
- 系统导出双格式：按模块、物料汇总
- 产品列表：加排序功能（SKU / 时间），加时间字段
- SKU 规范化：全表重生成、编辑页合规检查 + 列表标签
- 分类管理：默认折叠大类
- 新建成功后横幅：继续新增 / 返回列表
- 编辑页未保存拦截：产品 / 自产 / 报价 三页（纯原生 IIFE + 捕获阶段 delegate）
- 大型系统主材/配件 CSS Grid 竖对齐 + 表头（类型/名称/规格/单位/数量/单价/小计/操作）
- 主材：天蓝底 `bg-sky-100` + 输入框 `bg-sky-50` + `border-t border-sky-300`
- 配件：白底，无缩进（与主材同网格对齐），靠背景色+v分隔线区分主次
  - ⚠️ **待优化**：主材/配件视觉区分迭代多次（青/琥珀/蓝/白），走完确认流程，后续可再调
- 配件默认折叠：`_collapsed:true`，主材行 ▶/▼ 按钮切换，`addSub` 自动展开
- 模块顶部 + 系统底部：三数字——主材合计 / 配件合计 / 总额
- 大型系统主材默认外采、配件默认外采
- 配件名称选择后刷新不丢失修复（`_prodShow` 反查产品表）
- 报价/自产 列表页加 CSRF token 修复删不掉问题
- 指导售价 readonly 框加 hidden 字段修复保存为 0

 🔴 **待办：重启后** `git checkout systems.php && git pull` 同步锁住的本地仓库

### v4.2 — 毛利率定价模型 (2026-08-08)
- 定价从「系数」改为「毛利率」：指导售价 = 成本/(1-毛利率)
- DB 加 guide_margin_rate + min_margin_rate 列
- 全量数据迁移：外协 40/25，其它 30/15，自产 30/15
- 外采/自产/分类三端同步适配

### v4.1 — 首页 & 分类页优化 (2026-08-08)
- 首页：零成本信息、2×3 卡片、快捷入口、双列最近修改
- 分类页：折叠、▲▼排序替代拖拽、显示毛利率

### v4.0 — 报价系统上线 (2026-08-08)
- 报价单 CRUD、PDF/Excel 导出
- 自产产品 BOM 管理：三态（外采/自产/临时）
- 员工权限分层（不可看成本不可编辑）
- 综合进价 = 进价 + 其它费用

## 十三、相关参考

- **IOT 平台项目记录**：`C:\Users\MR NO.1\WorkBuddy\IOT平台\PROJECT.md`
  - 第 635 行记录了 2026-07-31「PMS旧系统清理」事件
- **服务器信息**：见 `IOT平台\PROJECT.md`「密码与凭证汇总」章节
- **对话规则**：见 `C:\Users\MR NO.1\.workbuddy\USER.md`「对话规矩」

## 十二、v4.0 报价系统（2026-08-08 阔也定价讨论）

**目标：** PMS 从"产品管理"升级为"产品 + 报价"一体化系统。

### 定价规则（每个产品独立可调，不搞全局统一）

| 类别 | 默认指导价系数 | 说明 |
|---|---|---|
| 外采产品 | 1.10 | 贴近市场价，防客户比价 |
| 外协加工 | 1.35 | 含设计成本分摊 |
| 自产设备 | 1.60 | 主利润仓，上限不超市场价×1.15（倒推法） |
| 施工安装 | 1.40 | 可单列，含差旅/现场管理 |
| 售后配件 | 1.15 | 保本微利 |

- 每个产品设置：**指导价系数**（默认值如上，单独可调）+ **最低售价**（绝对底线）
- 定价公式：建议售价 = 成本 × 指导价系数；实际成交价 ≥ 最低售价

### v4.0 报价系统功能设计

1. **整单报价**：支持一个项目多张报价单（按分区/功能拆分），从系统产品库选品 + 手动录入临时项
2. **报价单三层结构**：明细区 → 汇总区 → 商务条款（付款/质保/交期/有效期）
3. **公式防错**：报价单价、行小计、分类小计、总价、税费全自动计算
4. **商务条款模板**：付款方式、质保期、报价有效期统一格式

### 业务约束

- 设计费 / 项目管理费 / 商务隐形费用不单列，摊进自产设备单价
- 施工安装费可单列
- 质保 1 年，保修准备金摊进总报价
- 配件不单列模块，视为普通产品
- 政府项目售后预算紧张，售后不作为利润来源

### 当前系统现状

- 外采产品 96 个，平均毛利率 31.99%（偏高），大部分售价为 ¥0
- 自产产品 0 个，结构已支持 BOM + 成本 + 售价 + 毛利率
- 报价计算器为单品级，需升级为整单

### 人员分工

| 人员 | 角色 | 定价系统职责 |
|---|---|---|
| 阔也 | 总经理 | 定价规则 / 系数最终决策 |
| 邱工 | 产品与技术 | 自产设备成本底账（约 80% 准确 → 需补到 95%） |
| 钱希旸 | 业务 | 使用报价系统报价 |
| 严吴炜 | 项目实施 | 施工费报价参考 |

## 十三、版本控制

已配置 GitHub 部署（见第四节）。本地开发目录：
- `C:\Users\MR NO.1\WorkBuddy\佳培产品管理网页应用\extracted\` — v3.8 源码（本地 git 仓库）
- 每次改动：本地 `git commit` → `git push` → 服务器 `git pull`

---

**记录人**：小落
**最后更新**：2026-08-08 11:15（v4.0 价格区重写完成）

