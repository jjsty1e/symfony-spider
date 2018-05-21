<h1 align="center"> SYMFONY-SPIDER </h1>
<p align="center">
<a href="https://travis-ci.org/Jaggle/symfony-spider"><img src="https://travis-ci.org/Jaggle/symfony-spider.svg?branch=master"></a>
</p>
<p align="center">一个使用非常简单的多进程爬虫，基于php的symfony框架开发</p>

## 依赖服务

- php >= 5.6

## 安装

```bash
git clone git@github.com:Jaggle/symfony-spider.git spider
cd spider 
composer install
```

`composer  install`命令的最后，根据提示输入数据库配置（数据库名称现在给出，但是不需要现在建数据库，下面的命令会帮助自动创建数据库）
以及redis dsn(例如：redis://pass@localhost)。

## 创建数据库

```bash
php app/console doctrine:database:create
```

## 创建表结构
 
```bash
php app/console doctrine:schema:update --force --dump-sql
```

## 创建一个爬虫

```bash
php app/console spider:create
```

## 添加抓取规则

```
vim app/config/rules.json
```

**规则介绍：**

目前只能爬取四个字段，下面以爬取知乎为例：

```json
{
  "sf-spider" : {
    "linkRule": {
      "status": false,
      "rule": ""
    },
    "documentRule": {
      "title":  {
        "type": "text",
        "rule": "h1.QuestionHeader-title"
      },
      "meta": {
        "type": "href",
        "rule": ".UserLink-link"
      },
      "desc": {
        "type": "text",
        "rule": ".QuestionHeader-detail span.RichText.ztext"
      },
      "content": {
        "type": "html",
        "rule": "div.RichContent .RichContent-inner"
      }
    }
  }
}
```

## 运行爬虫

`SPIDER_NAME`为你创建的爬虫名称，例如我的为“sf-spider”。

```bash
php app/console spider:run --spiderName=SPIDER_NAME --workerCount=4 
```

or

```
php app/console spider:run SPIDER_NAME --workerCount=4 
```

or 开启日志输出

```
php app/console spider:run SPIDER_NAME --workerCount=4 --debug
```

> - workerCount: 启动的进程数量，默认为1
> - spiderName: 爬虫名称，默认"default"

-----

## 执行过程

```
               |  -- job进程<抓取网页内容>
master进程 -----| -- job进程<抓取网页内容>
               |  -- job进程<抓取网页内容>


任务队列 ----| -- 文档任务，分析网页，进行文档的入库操作
            | -- job任务，控制job的状态，给job进程分配job
```

