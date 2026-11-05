FROM php:8.4-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Install Microsoft ODBC Driver 18 and the PDO_SQLSRV extension used by this ERP.
RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS curl gnupg2 ca-certificates unixodbc unixodbc-dev \
    && curl -sSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 \
    && pecl install pdo_sqlsrv \
    && docker-php-ext-enable pdo_sqlsrv \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

# Serve only the public/ directory and allow its rewrite rules.
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && printf '\n<Directory /var/www/html/public>\n    Options FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf \
    && a2enmod rewrite headers

COPY . /var/www/html/
RUN install -m 0755 /var/www/html/docker-entrypoint.sh /usr/local/bin/erp-entrypoint \
    && chown -R www-data:www-data /var/www/html

EXPOSE 10000
CMD ["/usr/local/bin/erp-entrypoint"]
