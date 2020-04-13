<?php

// Create filestructure for php development project

$opts = getopt('n::p:');

$structure = $opts['n'];
$path = $opts['p'];
$root = trim($path, '/')."/".$structure;

// Create directories for project

if (!mkdir($root, 0777, true)) { die('Failed to create folders...'); }
if (!mkdir($root."/src", 0777, true)) { die('Failed to create folders...'); }
if (!mkdir($root."/docker", 0777, true)) { die('Failed to create folders...'); }
if (!mkdir($root."/docker/nginx", 0777, true)) { die('Failed to create folders...'); }

$php_fpm_file_name = $root."/docker/Dockerfile-php-fpm";
$php_fpm_file = <<<EOL

FROM php:fpm

RUN apt-get update
RUN apt-get install --yes --no-install-recommends zlib1g-dev libonig-dev \
      libzip-dev libpng-dev 

RUN docker-php-ext-install bcmath \
  && docker-php-ext-install zip \
  && docker-php-ext-install pdo_mysql \
  && docker-php-ext-install gd \
  && docker-php-ext-install mbstring \
  && docker-php-ext-install opcache

EOL;

$nginx_file_name = $root."/docker/nginx/default.conf";
$nginx_file = <<<EOL
    server {
        listen 80 default_server;
     
        root /usr/share/nginx/html;

	index index.html index.htm index.php;

        location / {
          try_files \$uri /index.php?\$query_string; # For Drupal >= 7
        }

        location @rewrite {
     	   rewrite ^/(.*)$ /index.php?q=$1;
        }
	
        location ~ \.php$ {
            try_files \$uri =404;
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            fastcgi_pass php:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_param PATH_INFO \$fastcgi_path_info;
        }
     
        error_log /var/log/nginx/api_error.log;
        access_log /var/log/nginx/api_access.log;
    }

EOL;

$docker_compose_name = $root."/docker-compose.yml";
$docker_compose = <<<EOL
version: '3'

services:
  db:
    image: mysql:5.7
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: $structure
      MYSQL_USER: dev
      MYSQL_PASSWORD: dev
    ports:
      - "3306:3306"
    stdin_open: true
    tty: true

  php:
    image: php:7.4-fpm
    build: 
      context: "./"
      dockerfile: docker/Dockerfile-php-fpm
    
    ports:
      - 9000:9000
    volumes:
      - ./src:/usr/share/nginx/html
    stdin_open: true
    tty: true

  web:
    image: nginx:1.17
    depends_on:
      - db
      - php
    volumes:
      - ./src:/usr/share/nginx/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    ports:
      - "8100:80"
    stdin_open: true
    tty: true

EOL;

$php_file_name = $root."/src/index.php";
$php_file = <<<EOL
<?php
phpinfo();

EOL;

file_put_contents($nginx_file_name, $nginx_file);
file_put_contents($php_fpm_file_name, $php_fpm_file);
file_put_contents($docker_compose_name, $docker_compose);
file_put_contents($php_file_name, $php_file);

echo <<<EOL

    run "cd $root && docker-compose up"
    Then visit http://localhost:8100

EOL;
