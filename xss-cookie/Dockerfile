ARG BASE_IMAGE=glassbox-base

FROM ${BASE_IMAGE}
RUN apt-get update && apt-get install -y python3 python3-selenium chromium chromium-driver && rm -rf /var/lib/apt/lists/*
COPY *.php *.py /var/www/html/
RUN cp /var/www/html/critical.php /var/www/html/critical.orig.php
RUN chown -R www-data:www-data /var/www/html
