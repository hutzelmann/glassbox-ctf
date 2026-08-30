# glassbox-harness — the shared web-delivery + Fix-editor skeleton.
# Every glass-box family (php, and later native) builds FROM this image, so it
# holds only what is common to all of them: the Apache/PHP runtime, pico.css,
# and fix.php. The concrete CodeMirror bundle that fix.php loads is supplied by
# the family layer on top, so the editor's language can differ per family.
FROM php:8.5-apache

RUN curl -Lo /var/www/html/pico.min.css https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css

COPY fix.php /var/www/html/
