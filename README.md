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

#### NGINX 
```
map $sent_http_content_type $expires {
    default                    off;
    text/html                  epoch;
    text/css                   max;
    application/javascript     max;
    ~image/                    max;
}

server {
    listen		80;
    server_name	example.ru;
    
    expires     $expires;
    
    charset		utf-8;
    access_log	/YOUR_ROOT_PATH/ru.example/nginx/access.log;
    root		/YOUR_ROOT_PATH/ru.example/public;
    
    location / {
        try_files $uri /index.php;
        autoindex on;
    }
    
    location ~ /uploads(/.*) {
        set $query $1;
        try_files /uploads$query /index.php;
    }
    
    location /robots.txt {
        add_header Content-Type text/plain;
        return 200 "User-agent: *\nDisallow: /\n";
    }
    
    error_page	500 502 503 504  /50x.html;
    location = /50x.html {
        root /usr/share/nginx/html;
    }
    
    location ~ \.php$ {
        fastcgi_pass                unix:/run/php/php7.0-fpm.sock;
        fastcgi_index               index.php;
        fastcgi_split_path_info     ^(.+.php)(.*)$;
        fastcgi_param               SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include                     fastcgi_params;
        fastcgi_read_timeout        300;
    }
}
```

#### License
The CMS Structure is licensed under the MIT license. See [License File](LICENSE.md) for more information.
