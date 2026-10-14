# Ambiente di COLLAUDO locale, non usare per il deploy di produzione.
FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf 'sendmail_path=/usr/bin/false\n' > /usr/local/etc/php/conf.d/staging-no-mail.ini
RUN printf '<Directory /var/www/html/public>\\n  Options FollowSymLinks\\n  AllowOverride All\\n  Require all granted\\n  FallbackResource /index.php\\n</Directory>\\n' > /etc/apache2/conf-available/mensa-staging.conf \
    && a2enconf mensa-staging
WORKDIR /var/www/html
COPY . /var/www/html
COPY staging/entrypoint.sh /usr/local/bin/mensa-staging-entrypoint.sh
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/config \
    && chmod 755 /usr/local/bin/mensa-staging-entrypoint.sh
ENTRYPOINT ["/bin/sh", "/usr/local/bin/mensa-staging-entrypoint.sh"]
CMD ["apache2-foreground"]
