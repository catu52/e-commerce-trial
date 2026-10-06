# Devcontainer Base Image: PHP 8.3 CLI with Node.js & Tooling
FROM php:8.3-fpm-bookworm

# Install System Dependencies and C Libraries required for Extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    librabbitmq-dev \
    supervisor \
    sqlite3 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Essential PHP Extensions for Postgres, Redis, RabbitMQ (AMQP), and Math
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip sockets

# Install Redis and AMQP via PECL
RUN pecl install redis amqp \
    && docker-php-ext-enable redis amqp

# Install Composer Globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /workspace
