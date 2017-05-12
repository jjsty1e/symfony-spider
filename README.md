<h1 align="center"> SYMFONY-SPIDER </h1>

一个简单的多进程爬虫，基于php的symfony框架开发

## 依赖服务

- php
- mysql
- redis


## 安装

```bash
git clone git@github.com:Jaggle/mysql-backup.git
cd spider 
composer install
```

根据提示输入数据库账号和密码

## 使用命令行创建数据库(已创建可略过)

``
php app/console doctrine:database:create
``

## 创建表结构
 
```
php app/console doctrine:schema:update --force --dump-sql

```

## 在 `app/config/parameters.yml` 添加 redis 配置：

```
parameters:
...
    snc_dsn: redis://localhost
...

```

## 创建一个爬虫
```
php app/console spider:create
```

## 添加抓取规则

```
vim app/config/rules.json
```

**规则介绍：**

目前只能爬取四个字段，可扩展字段配置正在开发中

```
{
  "default" : {  # 爬虫名字
    "linkRule": {    # 是否只抓取包含以下ruleurl
      "status": false,    # 是否启用linkRule
      "rule": ""    # url所需要包含的字符串
    },
    "documentRule": {
      "title":  {    # 标题
        "type": "text", # 类型 可选 text(纯文本)， html(富文本)， href,title等元素的属性
        "rule": "h1" # css选择器
      },
      "meta": {
        "type": "text", # ...
        "rule": ".meta" # ...
      },
      "desc": {
        "type": "text", # ...
        "rule": ".desc" # ...
      },
      "content": {
        "type": "html", # ...
        "rule": ".content" # ...
      }
    }
  }
}
```

## 运行爬虫
```
php app/console spider:run --jobCount=4 --spiderName=SPIDER_NAME
```

> jobCount: 启动的进程数量
> spiderName: 爬虫名称，默认"default"

或者 debug模式

```
php app/console app/console spider:run --debug
```


----


have fun!

