-- 分类加说明字段
ALTER TABLE categories ADD COLUMN description VARCHAR(200) DEFAULT '' COMMENT '说明';
