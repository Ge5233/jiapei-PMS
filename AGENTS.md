# AGENTS.md — 产品管理系统（PMS）

## 项目概览
- **类型**：内部产品资料 + 价格管理后台
- **技术栈**：PHP 8.2+ / MySQL 5.7+ / 原生 PHP（无框架）/ Tailwind CSS CDN / Alpine.js CDN
- **用户规模**：3-4 人
- **部署目标**：腾讯云轻量服务器 + 宝塔面板 + MySQL 5.7
- **访问方式**：服务器公网 IP + 端口 8081
- **运行方式**：开发用 `php -S` 内置服务器；生产用宝塔 PHP 站点（PHP-FPM + Nginx）

## 目录结构

```
/workspace/projects/
├── .coze                       # 沙箱启动配置
├── AGENTS.md                   # 本文件
├── DESIGN.md                   # 设计语言
├── README.md                   # 部署文档（小白版）
├── .htaccess                   # Apache 配置：禁止访问 includes/
├── index.php                   # 入口：未登录跳登录，已登录跳首页
├── install.php                 # 5 步安装向导（环境检查 → 配置 → 建表 → 创建账号 → 完成）
├── login.php                   # 登录页
├── logout.php                  # 退出
├── dashboard.php               # 首页（仪表盘）
├── products.php                # 产品列表（搜索/筛选/分页/导出）
├── product_edit.php            # 产品编辑/新增（+ 文件管理 + 价格历史 + 实时毛利计算 + 分类搜索）
├── categories.php              # 分类管理（A+B：换父级 + 拖拽排序）
├── supplier.php                # 供应商管理（列表/新增/编辑/删除）
├── users.php                   # 用户管理（仅管理员）
├── quote.php                   # 报价计算器
├── profile.php                 # 我的资料（改自己密码）
├── export.php                  # Excel 导出
├── api/                        # 内部 AJAX 接口
│   ├── product_save.php
│   ├── product_delete.php
│   ├── file_upload.php
│   ├── file_delete.php
│   ├── category_save.php
│   ├── category_delete.php
│   ├── category_reorder.php
│   ├── category_move.php
│   ├── supplier_save.php
│   ├── supplier_delete.php
│   └── user_save.php
├── includes/                   # 内部库（受 .htaccess 保护，不可直接访问）
│   ├── bootstrap.php           # 启动：加载配置、连接数据库、启动 session
│   ├── db.php                  # PDO 数据库封装
│   ├── auth.php                # 认证、权限检查
│   ├── functions.php           # 工具函数（HTML 转义、格式化、CSRF 等）
│   ├── models/
│   │   ├── User.php
│   │   ├── Product.php
│   │   ├── Category.php
│   │   ├── Supplier.php
│   │   ├── File.php
│   │   ├── PriceHistory.php
│   │   └── Log.php
│   └── views/
│       ├── header.php          # 顶部 + 左侧菜单
│       └── footer.php          # 底部 + JS
├── assets/
│   ├── app.css                 # 自定义样式（基于 Tailwind 工具类的微调）
│   └── app.js                  # 公共 JS（拖拽、上传、弹窗）
├── uploads/                    # 用户上传文件（产品资料）
│   └── .htaccess               # 禁止执行 PHP
└── sql/
    └── schema.sql              # 数据库结构（6 张表）
```

## 6 张数据表

| 表名 | 关键字段 |
|------|------|
| `users` | id, username, password_hash, name, role(admin/staff), status |
| `categories` | id, name, parent_id, sort_order |
| `products` | id, sku, name, category_id, spec, unit, cost_price, guide_price, min_discount, **supplier_id**, status, remark |
| `suppliers` | **id, name(唯一), contact, phone, email, address, bank_name, bank_account, license_no, remark, status** |
| `product_files` | id, product_id, original_name, stored_name, file_type, file_size, uploaded_by |
| `price_history` | id, product_id, field(cost_price/guide_price/min_discount), old_value, new_value, changed_by, remark |
| `operation_logs` | id, user_id, action, target_type, target_id, details, ip |

## 当前版本：v3.0

### 较 v2.0 改进
1. **折扣字段**：限制 0.01 ~ 1.00（不允许 > 1），UI 后缀显示百分号
2. **实时计算**：进价/售价/折扣变化时，实时显示"最低实际售价"和"最低实际毛利率"，<10% 红字
3. **供应商管理**：独立的 supplier 页面 + 模型 + API；产品可关联 supplier
4. **分类树形搜索 combobox**：产品编辑页分类下拉可输入关键字跨级筛选
5. **列表/仪表盘**：加"供应商"列、供应商统计卡片
6. **文件上传区**：删除/下载按钮补全 title 提示

### v3.0 升级脚本
- `sql/upgrade_v3.sql` 创建 suppliers 表 + 加 supplier_id 列
- 详见 `UPGRADE_V3.md`

## 开发规范

### PHP
- 使用 PHP 8.2+ 语法，类型声明尽量完整
- 数据库操作统一用 `includes/db.php` 的 PDO 封装
- 所有用户输入必须经过 `h()` 函数转义后再输出
- 所有写操作必须经过 CSRF token 验证
- 所有写操作必须写 `operation_logs`

### 前端
- 不用任何构建工具（无 webpack/vite）
- Tailwind CSS 和 Alpine.js 走 CDN
- 图标用 Lucide（CDN）
- 所有页面使用 `includes/views/header.php` 统一布局

### 命名
- 文件名：小写 + 下划线（`product_edit.php`）
- 函数名：小驼峰（`getProductList`）
- 类名：大驼峰（`ProductModel`）
- 数据库字段：小写 + 下划线（`cost_price`）

## 常用命令

```bash
# 启动开发服务器（沙箱中）
php -S 0.0.0.0:8081 -t /workspace/projects

# 验证数据库 schema
mysql -u root -p pms_db < sql/schema.sql

# 清理 uploads 测试文件
rm -rf uploads/*
```

## 安全要点

- 密码哈希用 `password_hash()` + `PASSWORD_DEFAULT`
- Session 有效期 2 小时
- CSRF token 每次写操作校验
- 文件上传限制：图片 5MB / PDF 10MB / Word/Excel 10MB
- 禁止上传可执行文件
- `includes/` 通过 `.htaccess` 禁止 HTTP 访问
- `uploads/` 通过 `.htaccess` 禁止 PHP 执行

## 部署

详见 `README.md`（宝塔部署文档）。
