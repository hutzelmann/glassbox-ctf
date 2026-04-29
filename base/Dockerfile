FROM node:alpine AS build
WORKDIR /build
COPY package.json .
RUN npm install
COPY cm-init.js cm-php-view.js cm-html-edit.js linters.js ./
RUN ./node_modules/.bin/esbuild cm-init.js --bundle --format=iife --outfile=codemirror-bundle.js && \
    ./node_modules/.bin/esbuild cm-php-view.js --bundle --format=iife --outfile=codemirror-php-view.js && \
    ./node_modules/.bin/esbuild cm-html-edit.js --bundle --format=iife --outfile=codemirror-html-edit.js

FROM php:8.5-apache

RUN curl -Lo /var/www/html/pico.min.css https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css

COPY --from=build /build/codemirror-bundle.js /var/www/html/codemirror-bundle.js
COPY --from=build /build/codemirror-php-view.js /var/www/html/codemirror-php-view.js
COPY --from=build /build/codemirror-html-edit.js /var/www/html/codemirror-html-edit.js
COPY fix.php lint.php /var/www/html/
