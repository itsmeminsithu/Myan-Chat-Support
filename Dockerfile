FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
 && docker-php-ext-install pdo_mysql mbstring \
 && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/myan-chat/app/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && a2enmod headers
WORKDIR /var/www/myan-chat
COPY app /var/www/myan-chat/app
COPY scripts /var/www/myan-chat/scripts
RUN mkdir -p /var/www/myan-chat/app/storage \
 && chown -R www-data:www-data /var/www/myan-chat/app/storage \
 && echo 'ServerTokens Prod' >> /etc/apache2/apache2.conf \
 && echo 'ServerSignature Off' >> /etc/apache2/apache2.conf
RUN printf '%s\n' 'expose_php=Off' 'display_errors=Off' 'log_errors=On' 'session.use_strict_mode=1' 'session.cookie_httponly=1' 'upload_max_filesize=4M' 'post_max_size=5M' > /usr/local/etc/php/conf.d/myanchat-security.ini
COPY docker-security.conf /etc/apache2/conf-enabled/myanchat.conf
