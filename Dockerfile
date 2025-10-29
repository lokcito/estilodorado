FROM php:8.2-fpm

# Instalación de extensiones del sistema requeridas y Nginx
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    nginx \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        soap

# Instala Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copiar archivos de dependencias para cache layer
COPY composer.json composer.lock ./

# Copiar resto del código
COPY . .

# Instalar dependencias sin dev y optimizar autoloaders
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Copiar configuración Nginx para Laravel
COPY nginx.conf /etc/nginx/sites-available/default

# Exponer puertos Nginx y php-fpm
EXPOSE 80 9000

# Ejecutar PHP-FPM y Nginx en primer plano (con supervisord o en background)
CMD service nginx start && php-fpm