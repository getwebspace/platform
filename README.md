![Release](https://img.shields.io/github/v/release/getwebspace/platform)
![Docker builder](https://github.com/getwebspace/platform/workflows/Docker%20builder/badge.svg)
![License](https://img.shields.io/github/license/getwebspace/platform)
![Visitors](https://visitor-badge.glitch.me/badge?page_id=getwebspace.platform)

## WebSpace Platform (WSE)

[Website](https://getwebspace.org/) |
[Documentation](https://github.com/getwebspace/platform/wiki) |
[Official Repository](https://github.com/getwebspace/platform) |
[Issue Tracker](https://github.com/getwebspace/platform/issues) |
[Docker template](https://github.com/getwebspace/platform-template) |
[Demo website here](https://demo.getwebspace.org)

![Demo site](image.jpeg)

Multi-user online stores designed for those who dislike complex online constructs.
Built on open source technology, it provides both flexibility and accessibility.

<details>
  <summary>Features</summary>

  - Static pages
  - Users:
    * Users
    * User groups
    * Permissions
    * Mailing list
  - Publications
    * Posts
    * Categories
  - Shop:
    * Catalogs
    * Products
    * Attributes
    * Orders
    * Statistic
  - Dynamic forms
  - Guestbook
  - Files and Image optimization (GD -> WebP)
  - Background tasks
  - Theme templates in Twig
  - Mailing, via SMTP
  - File editor:
    * Theme
    * Resource
  - Plugins API, types:
    * Default
    * OAuth
    * Delivery
    * Payment
    * Language (locale)
    * Legacy
  - HTTP API:
    * Dedicated REST API
    * Search API
    * Each public controller as API
    * Telemetry
  - Included reCAPTCHA
  - Latest PHP version
  - Latest dependencies
  - Docker compatible

  and more..
</details>

<details>
  <summary>Quickstart</summary>

  **Production mode**  
  [Installation instructions](https://github.com/getwebspace/platform/wiki/Installation-(Docker)) from Docker template [getwebspace/platform-template](https://github.com/getwebspace/platform-template)
  
  **Developer mode**  
  Clone repo and use docker by running the command: `make up`, then open `http://localhost:9000`
</details>

<details>
  <summary>Environment variables</summary>

  You can define the next environment variables
  
  | Type    | Variable Name      | Default        | description                                                       |
  |---------|--------------------|----------------|-------------------------------------------------------------------|
  | Build   | BUILD_DEPENDENCIES |                | Dependencies used in the build                                    |   
  | Build   | DEPENDENCIES       |                | Core Libraries                                                    |   
  | Build   | EXTRA_EXTENSIONS   | pdo_mysql      | Additional Libraries                                              |   
  | Build   | PLATFORM_HOME      | /var/container | Home directory                                                    |   
  | Runtime | DEBUG              | 0              | Debug mode                                                        |   
  | Runtime | TEST               | 0              | Test mode                                                         |   
  | Runtime | DATABASE           |                | PDO Database params (default: sqlite)                             |   
  | Runtime | SIMPLE_PHONE_CHECK | 0              | Checking the user's phone number for compliance with the standard |   
  | Runtime | TZ                 |                | TimeZone (default: UTC)                                           |   
  
  **Database Example**:
  `mysql://my_user:my_pass@127.0.0.0:3306/example`
</details>

<details>
  <summary>Makefile commands</summary>
  
  | Command               | Action                                        |
  |-----------------------|-----------------------------------------------|
  | `make up`             | Up                                            |
  | `make down`           | Down                                          |
  | `make run-test`       | PHPUnit test's                                |
  | `make run-lint`       | PHP Coding Standards automatically code fixer |
  | `make migrate-up`     | Phinx migration up                            |
  | `make migrate-down`   | Phinx migration rollback                      |
  | `make migrate-create` | Phinx create empty migration file             |
  | `make migrate-status` | Phinx check status                            |
</details>

<details>
  <summary>Scheduled jobs (for plugin authors)</summary>

  A job is a class extending `App\Domain\AbstractSchedule` with a `run()` method. A plugin schedules it in its constructor:

  ```php
  $this->addSchedule(\MyPlugin\Jobs\Cleanup::class, '0 3 * * *');                 // every night at 03:00
  $this->addSchedule(\MyPlugin\Jobs\Sync::class, '*/15 * * * MON-FRI', 'sync-work');  // a key: several schedules of one class
  $this->addSchedule(\MyPlugin\Jobs\Report::class, '@daily', null, true);          // catch up once after a downtime
  ```

  - The cron line is checked when the job is registered; a wrong one is logged and the job is not registered.
  - Times are in the time zone of the server (`TZ`, UTC by default).
  - A job that throws does not stop the other jobs; a job still running is not started twice.
  - A long task (`AbstractTask`) should call `setProgress()`: it is the sign of life, a task silent for 10 minutes is removed.
  - The state of the jobs (last and next run, error, switch on/off, run now) is in the panel: `/cup/schedule`.
  - After 3 failures in a row the channel `cron:job:failed` is published (`PubSub`) and a critical record is logged.
</details>

<details>
  <summary>Verified addons (plugins)</summary>

  | Themes                                                                | Plugins                                                                   | Languages                                                           |
  |-----------------------------------------------------------------------|---------------------------------------------------------------------------|---------------------------------------------------------------------|
  | [Default shop](https://github.com/getwebspace/platform-default-theme) | [Search optimization](https://github.com/getwebspace/platform-plugin-seo) | English                                                             |
  |                                                                       | [ClearCache](https://github.com/getwebspace/platform-plugin-clearcache)   | [Ukrainian](https://github.com/getwebspace/platform-lang-ukrainian) |
  |                                                                       | [Turbo PWA](https://github.com/getwebspace/platform-plugin-turbo)         | [Russian](https://github.com/getwebspace/platform-lang-russian)     |
</details>

## Collaborators

<a href="https://github.com/alksily"><img src="https://avatars.githubusercontent.com/u/5148853?v=4" alt="alksily" width="40"/></a>

## Copyright & license

Licensed under the MIT license. See [License File](LICENSE.md) for more information.
