# Ambiente di COLLAUDO locale, non usare per il deploy di produzione.
FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf 'sendmail_path=/usr/bin/false\n' > /usr/local/etc/php/conf.d/staging-no-mail.ini
WORKDIR /var/www/html
COPY . /var/www/html
COPY staging/entrypoint.sh /usr/local/bin/mensa-staging-entrypoint.sh
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/config \
    && chmod 755 /usr/local/bin/mensa-staging-entrypoint.sh
ENTRYPOINT ["/bin/sh", "/usr/local/bin/mensa-staging-entrypoint.sh"]
CMD ["apache2-foreground"]
