ARG BASE_IMAGE=glassbox-base

FROM ${BASE_IMAGE}

RUN apt update && apt install -y mariadb-server

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

COPY db.sql /var/
RUN service mariadb start && mariadb < /var/db.sql

COPY *.php /var/www/html/
RUN cp /var/www/html/critical.php /var/www/html/critical.orig.php
RUN chown -R www-data:www-data /var/www/html

CMD ["sh", "-c", "service mariadb start && apache2-foreground"]
