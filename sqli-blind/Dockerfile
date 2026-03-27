FROM php:8.2-apache

# TODO: Below should use apt command instead of hardcoded rf
RUN apt update && \
    apt install -y mariadb-server && \
    rm -rf /var/lib/apt/lists/*
    
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

COPY db.sql /var/
RUN service mariadb start && mariadb < /var/db.sql

COPY index.php /var/www/html/

CMD ["sh", "-c", "service mariadb start && apache2-foreground"]
