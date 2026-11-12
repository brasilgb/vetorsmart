FROM php:8.4-fpm
RUN apt-get update && apt-get install -y --no-install-recommends git curl libpng-dev libonig-dev libxml2-dev libzip-dev zip unzip && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# Node 22 para o build do front. O Vite roda `php artisan wayfinder:generate`, por isso o build
# precisa do PHP e do vendor no mesmo estágio. Node e node_modules são removidos ao final.
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -sf /usr/local/lib/node_modules/corepack/dist/corepack.js /usr/local/bin/corepack \
 && corepack enable
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction \
 && corepack enable && yarn install --frozen-lockfile \
 && yarn build \
 && mkdir -p /opt/public-build && cp -a public/build/. /opt/public-build/ \
 && rm -rf node_modules public/build \
 && rm -f /usr/local/bin/node /usr/local/bin/npm /usr/local/bin/corepack /usr/local/bin/yarn /usr/local/bin/yarnpkg /usr/local/bin/npx \
 && rm -rf /usr/local/lib/node_modules \
 && chown -R www-data:www-data storage bootstrap/cache
COPY docker-entrypoint.sh /usr/local/bin/infra-entrypoint
RUN chmod +x /usr/local/bin/infra-entrypoint
ENTRYPOINT ["infra-entrypoint"]
CMD ["php-fpm", "-F"]
