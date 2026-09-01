ARG BASE_IMAGE=glassbox-native

FROM ${BASE_IMAGE}

# The image publishes for both architectures like every other challenge. The
# exploitable binary is pinned to x86-64 (build.sh: nbuild --arch x86_64), so its
# addresses match the one shared walkthrough on every host; on an arm64 host the
# native family runs it under qemu-user. No per-challenge platform restriction.

# Only the files the running challenge needs, never README.md / solution.md.
COPY index.php glassbox.php main.c critical.c build.sh build.flags /var/www/html/

WORKDIR /var/www/html
# Snapshot the shipped snippet and flags for Restore, then build the initial binary.
RUN cp critical.c critical.orig.c \
    && cp build.flags build.orig.flags \
    && bash build.sh \
    && chown -R www-data:www-data /var/www/html

CMD ["apache2-foreground"]
