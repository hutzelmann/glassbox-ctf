FROM node:alpine AS cm-build
WORKDIR /build
COPY package.json .
RUN npm install
COPY cm-init.js cm-init-debug.js ./
RUN ./node_modules/.bin/esbuild cm-init.js --bundle --format=iife --outfile=codemirror-bundle.js && \
    ./node_modules/.bin/esbuild cm-init-debug.js --bundle --format=iife --outfile=codemirror-bundle-debug.js

FROM php:8.5-apache

RUN curl -Lo /var/www/html/pico.min.css https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css

COPY --from=cm-build /build/codemirror-bundle.js /var/www/html/codemirror-bundle.js
COPY --from=cm-build /build/codemirror-bundle-debug.js /var/www/html/codemirror-bundle-debug.js
COPY *.php *.html *.css /var/www/html/
RUN cp /var/www/html/critical.php /var/www/html/critical.orig.php
RUN chown -R www-data:www-data /var/www/html
