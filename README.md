![Docker builder](https://github.com/getwebspace/platform/workflows/Docker%20builder/badge.svg)
![License](https://img.shields.io/github/license/getwebspace/platform)
![](https://visitor-badge.glitch.me/badge?page_id=getwebspace.platform)
[![Telegram](https://img.shields.io/badge/chat-on%20Telegram-2ba2d9.svg)](https://t.me/WSEPlatformCommunity)

## WebSpace Engine
[Website](https://getwebspace.org/) |
[Documentation](https://github.com/getwebspace/platform/wiki) |
[Official Repository](https://github.com/getwebspace/platform) |
[Doker template](https://github.com/getwebspace/platform-template) |
[Demo shop](https://demo.getwebspace.org)

WSE is a free open source multi-user site engine with great functionality, primarily intended for: organization of mass media; blogs; online stores;

![Demo site](https://getwebspace.org/resource/img/showcase/publication.png)

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
- SMTP & SendPulse
- reCAPTCHA
- OAuth (facebook, vk)
- Files and image optimization (imagemagick)
- Background tasks

and more..

## Plugins
- Search optimization (robots.txt, sitemap, Yandex.Market, Google Merchant)
- Import products from CommerceML
- TradeMaster

## Languages
- English (default)
- Ukrainian
- Russian

## Roadmap

- new attributes

## Installation
#### Production mode
[Installation instructions](https://github.com/getwebspace/platform/wiki/Installation-(Docker)) from Docker template [getwebspace/platform-template](https://github.com/getwebspace/platform-template)

#### Developer mode
Use docker by running the command: `docker-compose up -d`, then open `http://localhost:9000`

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
./phpunit [..]
```

#### Environment variables
You can define the next environment variables to change values from NGINX and PHP

| Server | Variable Name           | Default | description                                                                                                                                                                                                                    |
|--------|-------------------------|---------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| NGINX  | client_max_body_size    | 2m      | Sets the maximum allowed size of the client request body, specified in the “Content-Length” request header field.                                                                                                              |
| PHP    | clear_env               | no      | Clear environment in FPM workers. Prevents arbitrary environment variables from reaching FPM worker processes by clearing the environment in workers before env vars specified in this pool configuration are added.           |
| PHP    | max_execution_time      | 0       | Maximum time in seconds a script is allowed to run before it is terminated by the parser. This helps prevent poorly written scripts from tying up the server. The default setting is 30.                                       |
| PHP    | max_input_time          | -1      | Maximum time in seconds a script is allowed to parse input data, like POST, GET and file uploads.                                                                                                                              |
| PHP    | max_input_vars          | 1000    | Maximum number of input variables allowed per request and can be used to deter denial of service attacks involving hash collisions on the input variable names.                                                                |
| PHP    | memory_limit            | 128M    | Maximum amount of memory in bytes that a script is allowed to allocate. This helps prevent poorly written scripts for eating up all available memory on a server. Note that to have no memory limit, set this directive to -1. |
| PHP    | post_max_size           | 8M      | Max size of post data allowed. This setting also affects file upload. To upload large files, this value must be larger than upload_max_filesize. Generally speaking, memory_limit should be larger than post_max_size.         |
| PHP    | upload_max_filesize     | 2M      | Maximum size of an uploaded file.                                                                                                                                                                                              |
| WSE    | DEBUG                   | 0       | Deep Application Debugging Mode                                                                                                                                                                                                |   
| WSE    | DATABASE                |         | Checking the user's phone number for compliance with the standard                                                                                                                                                              |   
| WSE    | SIMPLE_PHONE_CHECK      | 0       | PDO Database params                                                                                                                                                                                                            |   

#### After run
Check chmod's

```shell script
chmod -R 0777 plugin
chmod -R 0777 theme
chmod -R 0777 var
chmod -R 0776 var/upload
```

## License
Licensed under the MIT license. See [License File](LICENSE.md) for more information.
