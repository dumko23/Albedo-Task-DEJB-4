FROM php:8.1

WORKDIR /usr/src/myapp

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN docker-php-ext-configure pcntl --enable-pcntl \
  && docker-php-ext-install \
    pcntl

RUN pecl install -o -f redis \
&&  rm -rf /tmp/pear \
&&  docker-php-ext-enable redis

CMD [ "php", "./App/index.php" ]

