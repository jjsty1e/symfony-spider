<h1 align="center"> SYMFONY-SPIDER </h1>
<p align="center">
<a href="https://travis-ci.org/Jaggle/symfony-spider"><img src="https://travis-ci.org/Jaggle/symfony-spider.svg?branch=master"></a>
</p>
<p align="center">一个使用非常简单的多进程爬虫，基于php的symfony框架开发</p>

## 依赖服务

- php >= 5.6
- mysql = 5.6
- redis


## 安装

```bash
git clone git@github.com:Jaggle/symfony-spider.git spider
cd spider 
composer install
```

`composer  install`命令的最后，根据提示输入数据库配置以及redis dsn

## 创建数据库(已创建可略过)

```
php app/console doctrine:database:create
```

## 创建表结构
 
```
php app/console doctrine:schema:update --force --dump-sql
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
  "default" : {  # 刚才创建的爬虫名字
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
php app/console spider:run --spiderName=SPIDER_NAME --workerCount=4 
```

or

```
php app/console spider:run SPIDER_NAME --workerCount=4 
```

or with debug

```
php app/console app/console spider:run SPIDER_NAME --workerCount=4 --debug
```

> - workerCount: 启动的进程数量，默认为1
> - spiderName: 爬虫名称，默认"default"


-----

have fun!

