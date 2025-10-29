# Etapa 1: Construcción de dependencias
FROM php:8.2-fpm as builder

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Crear directorio de la app
WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . .

# Instalar dependencias de Laravel sin dev
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Generar cachés de Laravel
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

# Etapa 2: Imagen final para producción
FROM php:8.2-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev libzip-dev unzip zip curl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copiar el build desde la primera etapa
COPY --from=builder /var/www/html /var/www/html

# Permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8000

# Comando de inicio
CMD php artisan serve --host=0.0.0.0 --port=8000