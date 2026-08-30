# glassbox-php — the PHP challenge family.
# Builds FROM the harness and adds everything PHP-specific: the CodeMirror editor
# bundles (PHP/HTML/SQL modes) and PHP static analysis (Psalm + lint.php).
# Web challenges (SQLi, XSS) build FROM this image.
ARG BASE_IMAGE=glassbox-harness

FROM node:alpine AS build
WORKDIR /build
COPY editor/package.json .
RUN npm install
COPY editor/cm-init.js editor/cm-php-view.js editor/cm-html-edit.js editor/cm-sql-edit.js editor/linters.js ./
RUN ./node_modules/.bin/esbuild cm-init.js --bundle --format=iife --outfile=codemirror-bundle.js && \
    ./node_modules/.bin/esbuild cm-php-view.js --bundle --format=iife --outfile=codemirror-php-view.js && \
    ./node_modules/.bin/esbuild cm-html-edit.js --bundle --format=iife --outfile=codemirror-html-edit.js && \
    ./node_modules/.bin/esbuild cm-sql-edit.js --bundle --format=iife --outfile=codemirror-sql-edit.js

FROM ${BASE_IMAGE}

RUN curl -L https://github.com/vimeo/psalm/releases/latest/download/psalm.phar \
      -o /usr/local/bin/psalm \
    && chmod +x /usr/local/bin/psalm \
    && mkdir -p /var/cache/psalm \
    && chown www-data:www-data /var/cache/psalm

COPY --from=build /build/codemirror-bundle.js /var/www/html/codemirror-bundle.js
COPY --from=build /build/codemirror-php-view.js /var/www/html/codemirror-php-view.js
COPY --from=build /build/codemirror-html-edit.js /var/www/html/codemirror-html-edit.js
COPY --from=build /build/codemirror-sql-edit.js /var/www/html/codemirror-sql-edit.js
COPY lint.php psalm.xml /var/www/html/
