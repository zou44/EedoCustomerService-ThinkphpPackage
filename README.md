# EedoCustomerService(客服系统)

## 注意
*  该项目还处于BETA阶段，建议能理解代码的用户使用。
*  BETA阶段的迭代不保证会向下兼容。

## 为了解决
客服系统对于大部分PHP项目开发者来说，不常用但是要时又找不合适的。
自己开发又不划算，找开源又会因为各种协议OR质量问题烦恼.
对,所以这个项目出现了, 通过 composer 就可以快速的将一个客服系统集成到你的项目里。
### 作者的教训
之前工作接手过很多有客服模块的项目，这些项目的特点就是客服模块都是以单独的站点存在，它们没有很好的集成到原项目中，迁移时可能会忘掉又或因缺少文档需要阅读源码。很是折磨人。

## 迭代周期
BETA阶段为两周一迭代。

## 开发计划
1. 用户端 & 客服端模板 (进行中)
2. 解耦数据存储并支持更多驱动 & 支持无存储驱动运行 (进行中)
3. 发布一个集成了该依赖的TP项目实例 & 支持DOCKER
4. 兼容WIN
5. 增加环境检测脚本

## 部署注意
* 安装前请先阅读workerman 安装OR解除禁用函数 [传送门](https://www.workerman.net/doc/workerman/faq/disable-function-check.html)
* 暂时只支持linux下运行
* mysql >=5.6
* thinkphp >=5.1
* php >=5.6
* thinkphp的debug打开状态下运行项目(php think eedo)会启动文件监听(热更新)

## 部署 
### 第一步 | 安装依赖
composer require 
### 第二步 | 配置参数
安装完成后config目录会多出eedo字眼的文件,配置好它们。  
### 第三步 | 创建数据库
在根目录下运行以下命令
```shell
配置好eedo_phinx就可以使用这个命令
./vendor/bin/phinx migrate  -c ./config/eedo_phinx.php

如果你没有配置eedo_phinx文件可以使用这个命令
export EEDO_PHINX_HOST=数据库地址 EEDO_PHINX_NAME=数据库名 EEDO_PHINX_USER=数据库用户名 EEDO_PHINX_PASS=数据库密码;  ./vendor/bin/phinx migrate  -c ./config/eedo_phinx.php
```
### 第四步 | 填充数据
在根目录下运行以下命令
```shell
配置好eedo_phinx就可以使用这个命令
./vendor/bin/phinx seed:run -c ./config/eedo_phinx.php

如果你没有配置eedo_phinx文件可以使用这个命令
export EEDO_PHINX_HOST=数据库地址 EEDO_PHINX_NAME=数据库名 EEDO_PHINX_USER=数据库用户名 EEDO_PHINX_PASS=数据库密码;  ./vendor/bin/phinx seed:run -c ./config/eedo_phinx.php
```

### 第五步 | 启动
在根目录下运行以下命令
```shell
php think eedo
其他命令可
php think eedo -h 
```