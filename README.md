WebSpace Engine
====
_(Content Management System)_

[![Build Status](https://travis-ci.com/0x12f/platform.svg?branch=master)](https://travis-ci.com/0x12f/platform)

Многофункциональная система управления сайтом,
в первую очередь предназначена для:
* организации средств массовой информации;
* блогов;
* интернет-магазинов;

#### License
Licensed under the MIT license. See [License File](LICENSE.md) for more information.

#### Production mode
[Инструкция по установке](https://github.com/0x12f/platform/wiki/Установка-(Docker)) в Docker из шаблона [0x12f/platform-template](https://github.com/0x12f/platform-template)  

#### Developer mode
Воспользуйтесь `Docker` выполнив команду: `docker-compose up -d`

*Установка зависимостей*
```shell script
./composer install
```

*Статический анализатор*
```shell script
./phpcs
```

*Unit tests*
```shell script
./phpunit
```
