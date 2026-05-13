# PHP 8.2 + Apache — MT Auth (MySQL + MongoDB + Redis)
FROM php:8.2-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    ca-certificates \
    git \
    unzip \
    libssl-dev \
    pkg-config \
    libzip-dev \
    zlib1g-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install -j"$(nproc)" mysqli zip \
    && pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis \
    && curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# vendor/ is in .dockerignore — install dependencies in the image
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .

RUN composer dump-autoload --optimize

RUN a2enmod rewrite headers \
    && printf '%s\n' \
      '<Directory /var/www/html>' \
      '  AllowOverride All' \
      '  Require all granted' \
      '</Directory>' \
      > /etc/apache2/conf-available/docker-app.conf \
    && a2enconf docker-app

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1/index.html >/dev/null || exit 1

CMD ["apache2-foreground"]
