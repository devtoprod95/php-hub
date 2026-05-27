FROM php:7.4-fpm

ENV TZ=Asia/Seoul

RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    git \
    curl \
    tzdata \
    supervisor && \
    docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Node.js 16 설치 (PHP 7.4 매칭용)
RUN curl -fsSL https://deb.nodesource.com/setup_16.x | bash - \
    && apt-get install -y nodejs

# OS 시간대 설정
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 라라벨 필수 PHP 확장 모듈 설치 (MySQL 관련 모듈 완전 제외)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mbstring zip exif pcntl gd

# PHP 자체 타임존 설정
RUN echo "date.timezone = Asia/Seoul" > /usr/local/etc/php/conf.d/timezone.ini

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html