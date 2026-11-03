# Ambiente di COLLAUDO locale, non utilizzare per il deploy di produzione.
FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf 'sendmail_path=/usr/bin/false\n' > /usr/local/etc/php/conf.d/staging-no-mail.ini \
    && printf '<Directory /var/www/html/public>\nOptions FollowSymLinks\nAllowOverride All\nRequire all granted\nFallbackResource /index.php\n</Directory>\n' > /etc/apache2/conf-available/mensa-staging.conf \
    && a2enconf mensa-staging
WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/config
ENTRYPOINT ["/bin/sh", "/var/www/html/staging/entrypoint.sh"]
CMD ["apache2-foreground"]
