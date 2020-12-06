WebSpace Engine
====
_(Content Management System)_

![Docker builder](https://github.com/0x12f/platform/workflows/Docker%20builder/badge.svg)

Multifunctional content management system,
primarily intended for:
* organization of mass media;
* blogs;
* online stores;

#### License
Licensed under the MIT license. See [License File](LICENSE.md) for more information.

#### Production mode
[Installation instructions](https://github.com/0x12f/platform/wiki/Installation-(Docker)) from Docker template [0x12f/platform-template](https://github.com/0x12f/platform-template)  

#### Developer mode
Use `Docker` by running the command: `docker-compose up -d`

*Install dependencies*
```shell script
./composer install
```

*Static analyzer*
```shell script
./phpcs
```

*Unit tests*
```shell script
./phpunit
```
