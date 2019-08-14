CMS Structure
====
Content Management System structure by 0x12f

#### Requirements
- PHP >= 7.0

#### Installation

Run this command from the directory in which you want to install.

```
composer create-project 0x12f/cms-structure [my-app-name]
```

Replace `[my-app-name]` with the desired directory name for your new application.

#### Doctrine

```
php engine/libs/bin/doctrine
php engine/libs/bin/doctrine orm:schema-tool:create
php engine/libs/bin/doctrine orm:schema-tool:update
```

#### License
The CMS Structure is licensed under the MIT license. See [License File](LICENSE.md) for more information.
