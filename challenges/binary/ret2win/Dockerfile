ARG BASE_IMAGE=glassbox-native

FROM ${BASE_IMAGE}

# x86-64 addresses in the walkthrough must match the shipped binary, so this
# challenge publishes for amd64 only; arm64 hosts run it under emulation. The CI
# discover job reads this label.
LABEL org.glassbox.platforms="linux/amd64"

# Only the files the running challenge needs, never README.md / solution.md.
COPY index.php glassbox.php main.c critical.c build.sh build.flags /var/www/html/

WORKDIR /var/www/html
# Snapshot the shipped snippet and flags for Restore, then build the initial binary.
RUN cp critical.c critical.orig.c \
    && cp build.flags build.orig.flags \
    && bash build.sh \
    && chown -R www-data:www-data /var/www/html

CMD ["apache2-foreground"]
