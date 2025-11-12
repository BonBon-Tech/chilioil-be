# ChiliOil Backend - Development PHP Image
FROM php:8.2-fpm

# Arguments
ARG USER=www-data
ARG GROUP=www-data
ARG UID=1000
ARG GID=1000
ARG INSTALL_XDEBUG=false

# Set working directory
WORKDIR /var/www

# Install system dependencies and PHP extensions in single layer
RUN apt-get update && apt-get install -y \
    curl \
    g++ \
    git \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    pkg-config \
    unzip \
    zip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# (Optional) Xdebug
RUN if [ "$INSTALL_XDEBUG" = "true" ]; then \
    pecl install xdebug && docker-php-ext-enable xdebug && \
    echo "xdebug.mode=develop,debug" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini ; \
    fi

# Copy composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy only composer files first for caching
COPY composer.json composer.lock ./

# Install dependencies (ignore scripts for speed in dev)
RUN composer install --no-interaction --prefer-dist --no-scripts --no-progress

# Copy application source (will be overridden by bind mount in dev)
COPY . .

# Add entrypoint script
COPY docker/php/entrypoint.sh /usr/local/bin/project-entrypoint
RUN chmod +x /usr/local/bin/project-entrypoint

# Ensure proper permissions (will be re-applied on container start if needed)
RUN chown -R ${USER}:${GROUP} /var/www \
    && find storage -type d -exec chmod 775 {} \; \
    && chmod -R 775 bootstrap/cache

# Expose php-fpm port
EXPOSE 9000

# Healthcheck (simple php -v)
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php -v || exit 1

ENTRYPOINT ["/usr/local/bin/project-entrypoint"]
