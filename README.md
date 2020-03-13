Информация о проекте
====
**WebSpace Engine** (Content Management System) многофункциональная система управления сайтом,
в первую очередь предназначена для:
* организации средств массовой информации;
* блогов;
* интернет-магазинов;

#### Переменные окружения
`DEBUG` - true для режима отладки;  
`SALT` - секретная комбинация для безопасности;  
`SIMPLE_PHONE_CHECK` - true для упрощенной проверки телефона;  
`DATABASE` - DSN для подключения к базе данных;  
`SENTRY` - DSN для [Sentry.io](https://sentry.io);  

#### Права на папки и файлы
```shell script
chmod -R 0777 resource
chmod -R 0777 theme
chmod -R 0777 var
chmod 0777 var/database.sqlite
```

#### Production mode
Воспользуйтесь готовым шаблоном: [0x12f/platform-template](https://github.com/0x12f/platform-template)

#### Developer mode
Воспользуйтесь `Docker` выполнив команду: `docker-compose -f docker-compose.yml`

#### Инициализация схемы базы данных
```shell script
docker-compose run platform vendor/bin/doctrine orm:schema-tool:create
```

##### Обновление схемы базы данных
```shell script
docker-compose run platform vendor/bin/doctrine orm:schema-tool:update --force
```

#### Добавление пользователя с правами администратора
Логин: `admin`  
E-Mail: `admin@example.com`  
Пароль: `111222`

```shell script
docker-compose run platform vendor/bin/doctrine dbal:run-sql "INSERT INTO user_session (uuid) VALUES ('00000000-0000-0000-0000-000000000000');"
docker-compose run platform vendor/bin/doctrine dbal:run-sql "INSERT INTO user (uuid, username, email, password, status, level) VALUES ('00000000-0000-0000-0000-000000000000', 'admin', 'admin@example.com', '4b60602435c81eca6516601b68219c37f93de49c1192660aaa16066070e23b352fb0578b30cb588bb416b5138f03511a809f8b6610d20d90bf72d2a4d9e9548e06cd3eec8ed6', 'work', 'admin');"
```

#### License
Licensed under the MIT license. See [License File](LICENSE.md) for more information.
