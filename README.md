
# WebSpace Engine

![Docker builder](https://github.com/getwebspace/platform/workflows/Docker%20builder/badge.svg)

WebSpace Engine - is a free open source multi-user site engine with great functionality, primarily intended for: organization of mass media; blogs; online stores;

![Demo site](https://getwebspace.org/resource/img/revolutionize/2.png)

## Features

- Templates in Twig
- Template editor
- Plugins API
- HTTP API (dedicated API, and each public controller as API)
- Docker compatible
- Publications
- Static pages
- Catalog of products (shop)
- Dynamic forms
- Guestbook
- Users and User groups with permissions
- User mailing list
- OAuth (facebook, vk)
- Files and image optimization (imagemagick)
- Background tasks

and more..

## Plugins

- Search optimization (robots.txt, sitemap, Yandex.Market, Google Merchant)
- Clear cache
- Import products from CommerceML
- 1C Bitrix CRM integration
- TradeMaster

## Roadmap

- english language support
- ukrainian language support
- more plugins

## Demo

Check out our [demo shop](https://getwebspace.org) [russian]

## Installation
#### Production mode
[Installation instructions](https://github.com/getwebspace/platform/wiki/Installation-(Docker)) from Docker template [getwebspace/platform-template](https://github.com/getwebspace/platform-template)

#### Developer mode
Use docker by running the command: `docker-compose up -d`

*Install dependencies*
```shell script
./composer install
```

*Static analyzer*
```shell script
./phpcs
```

*Migrations*
```shell script
./migration [..]
```

*Unit tests*
```shell script
./phpunit [..]
```

## License

Licensed under the MIT license. See [License File](LICENSE.md) for more information.
