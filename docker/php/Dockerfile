FROM php:8.4-fpm

# ── System dependencies ───────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
        git \
        curl \
        libpq-dev \
        libzip-dev \
        libonig-dev \
        zip \
        unzip \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        zip \
        pcntl \
        bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Composer ─────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── PHP config ───────────────────────────────────────────────────────
RUN echo "upload_max_filesize=20M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=20M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/backend

# Default: run PHP-FPM (worker overrides this via compose command:)
CMD ["php-fpm"]
