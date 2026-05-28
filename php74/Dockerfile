FROM php:7.4-fpm

ENV TZ=Asia/Seoul

# 1. 시스템 패키지 설치
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libssl-dev \
    zip \
    unzip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    git \
    curl \
    tzdata \
    supervisor && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Swoole 설치 (PHP 7.4 호환 버전 명시)
# openswoole은 PHP 7.4 지원이 4.x대까지이므로 버전 고정
RUN pecl channel-update pecl.php.net && \
    pecl install swoole-4.8.13 && \
    pecl install openswoole-4.12.1 && \
    docker-php-ext-enable swoole

# 3. Node.js 16 설치
RUN curl -fsSL https://deb.nodesource.com/setup_16.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 4. 시간대 설정
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone && \
    echo "date.timezone = Asia/Seoul" > /usr/local/etc/php/conf.d/timezone.ini

# 5. Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Supervisor 설정
COPY ./swoole/swoole-worker.conf /etc/supervisor/conf.d/swoole-worker.conf

WORKDIR /var/www/html

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]