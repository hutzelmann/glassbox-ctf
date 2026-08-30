ARG BASE_IMAGE=glassbox-native

FROM ${BASE_IMAGE}

# x86-64 addresses in the walkthrough must match the shipped binary, so this
# challenge publishes for amd64 only; arm64 hosts run it under emulation.
LABEL org.glassbox.platforms="linux/amd64"

COPY index.php glassbox.php main.c critical.c build.sh build.flags /var/www/html/

WORKDIR /var/www/html
# The flag is a file only a spawned shell can read, the challenge never prints it.
RUN cp critical.c critical.orig.c \
    && cp build.flags build.orig.flags \
    && bash build.sh \
    && printf 'R0pP4stTh3NX\n' > /flag \
    && chmod 644 /flag \
    && chown -R www-data:www-data /var/www/html

CMD ["apache2-foreground"]
