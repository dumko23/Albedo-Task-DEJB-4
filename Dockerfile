
FROM php:8.1
COPY . /usr/src/myapp
WORKDIR /usr/src/myapp

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN docker-php-ext-configure pcntl --enable-pcntl \
  && docker-php-ext-install \
    pcntl

CMD [ "php", "./App/parser-test2.php" ]

