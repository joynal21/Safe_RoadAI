FROM php:8.2-apache

# PHP extensions used by SafeRoad (MySQL + reverse-geocoding HTTP requests).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev ca-certificates \
    && docker-php-ext-install mysqli pdo pdo_mysql curl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Allow project-level Apache rules from .htaccess.
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY docker/saferoad.ini /usr/local/etc/php/conf.d/saferoad.ini
COPY docker/render-entrypoint.sh /usr/local/bin/render-entrypoint
COPY . /var/www/html/

# Runtime upload directory. Note: Render Free storage is ephemeral.
RUN mkdir -p /var/www/html/assets/uploads/reports \
    && chown -R www-data:www-data /var/www/html \
    && chmod +x /usr/local/bin/render-entrypoint

EXPOSE 10000

CMD ["render-entrypoint"]
