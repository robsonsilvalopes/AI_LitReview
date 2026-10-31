# -----------------------------------------------------------------------------
# php-base: PHP-FPM + extensões (reutilizado pelo estágio da aplicação)
# laravel-app: código + composer + Vite (imagem usada por app e queue no Coolify)
# web: nginx + mesma árvore /var/www/html (sem bind mount no host Coolify)
# Em desenvolvimento local, docker-compose.yml usa build.target: laravel-app
# -----------------------------------------------------------------------------

FROM php:8.4-fpm AS php-base

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
  build-essential \
  libpng-dev \
  libjpeg62-turbo-dev \
  libfreetype6-dev \
  locales \
  zip \
  jpegoptim optipng pngquant gifsicle \
  vim \
  unzip \
  git \
  curl \
  libzip-dev \
  libonig-dev \
  poppler-utils \
  && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
  && apt-get install -y nodejs

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl opcache
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY ./docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY ./docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

FROM php-base AS laravel-app

ARG APP_KEY=base64:cHV0LXlvdXIta2V5LWZvci1idWlsZC1vbmx5LXJlcGxhY2U=
ENV APP_KEY=${APP_KEY}
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    NPM_CONFIG_UPDATE_NOTIFIER=false \
    NPM_CONFIG_FETCH_RETRIES=5 \
    NPM_CONFIG_FETCH_RETRY_MINTIMEOUT=20000

COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
  && npm ci --legacy-peer-deps \
  && npm run build \
  && rm -rf node_modules \
  && chmod +x /var/www/html/scripts/coolify-migrate.sh \
  && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

FROM nginx:alpine AS web

RUN apk add --no-cache curl

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=laravel-app /var/www/html /var/www/html

EXPOSE 80
# Coolify / Docker: health do contentor HTTP (Laravel `bootstrap/app.php` health: '/up')
HEALTHCHECK --interval=15s --timeout=5s --start-period=15s --retries=5 \
  CMD curl -fsS http://127.0.0.1/up >/dev/null || exit 1

CMD ["nginx", "-g", "daemon off;"]

# Estágio final sem nome: `docker build` sem --target continua a produzir PHP-FPM (dev/local).
FROM laravel-app
