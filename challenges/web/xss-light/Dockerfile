ARG BASE_IMAGE=glassbox-php

FROM ${BASE_IMAGE}
COPY *.php /var/www/html/
RUN cp /var/www/html/critical.php /var/www/html/critical.orig.php
RUN chown -R www-data:www-data /var/www/html
