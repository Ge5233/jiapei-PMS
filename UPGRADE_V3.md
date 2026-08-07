# PMS v3.0 升级指南

> 适用：v2.0（pms_v2.zip） → v3.0（pms_v3.zip）

## 升级内容

| 改进点 | 说明 |
|--------|------|
| 1. 折扣字段 | 改为 0.01 ~ 1.00，UI 后缀显示百分号 |
| 2. 实时计算 | 售价/折扣/进价变动时，实时显示最低实际售价、最低实际毛利率（<10% 红字） |
| 3. 供应商模块 | 新增「供应商管理」菜单 + 产品可关联供应商 |
| 4. 分类搜索 | 产品编辑页分类下拉改为可输入关键字筛选的树形 combobox |
| 5. 列表加列 | 产品列表新增「供应商」列，仪表盘新增供应商统计 |

## 升级步骤（SSH 操作）

```bash
# 1. 备份现有库
mysqldump -u pms_user -p pms_db > /www/backup/pms_db_v2_$(date +%Y%m%d).sql

# 2. 备份现有网站
cp -r /www/wwwroot/pms /www/backup/pms_v2_$(date +%Y%m%d)

# 3. 上传升级 SQL
cd /www/wwwroot/pms
mysql -u pms_user -p pms_db < sql/upgrade_v3.sql

# 4. 覆盖网站文件
#    在宝塔文件管理中上传 pms_v3.zip 并解压覆盖到 /www/wwwroot/pms
#    或者 scp 上传：
#    scp pms_v3.zip root@101.43.8.128:/www/wwwroot/pms/
#    ssh 到服务器
#    cd /www/wwwroot/pms && unzip -o pms_v3.zip && rm pms_v3.zip

# 5. 修权限
chown -R www:www /www/wwwroot/pms
chmod -R 755 /www/wwwroot/pms
chmod -R 777 /www/wwwroot/pms/uploads

# 6. 清理浏览器缓存并刷新页面即可
```

## 数据库变更详情

```sql
-- 新建 suppliers 表
CREATE TABLE suppliers (
  id, name (唯一), contact, phone, email, address,
  bank_name, bank_account, license_no, remark, status,
  created_at, updated_at
);

-- products 加字段
ALTER TABLE products ADD COLUMN supplier_id INT UNSIGNED AFTER min_discount;
```

## 不需要动的

- `.env` 配置不变
- `installed.lock` 不动
- `uploads/` 已上传的文件不动
- 数据库已存的产品、用户、分类、价格历史都不动
- 旧的 `install.php` 如果已删除，**不要重新放回**

## 升级后

- 左侧菜单「业务」组下多出 **「供应商管理」**
- 产品编辑页：折扣字段、实时计算、供应商下拉、分类搜索全部生效
- 旧产品的 `min_discount` 值（0.01 ~ 1.00 范围）会自动正常显示
- `min_discount` > 1 的旧数据（如果有）会在产品编辑时被限定到 1.00

## 回滚（如需）

```bash
# 1. 还原数据库
mysql -u pms_user -p pms_db < /www/backup/pms_db_v2_*.sql

# 2. 还原网站文件
rm -rf /www/wwwroot/pms
mv /www/backup/pms_v2_* /www/wwwroot/pms

# 3. 重启 PHP-FPM
systemctl restart php-fpm-82
```
