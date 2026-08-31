ARG BASE_IMAGE=glassbox-native

FROM ${BASE_IMAGE}

# x86-64 addresses in the walkthrough must match the shipped binary, so this
# challenge publishes for amd64 only; arm64 hosts run it under emulation.
LABEL org.glassbox.platforms="linux/amd64"

COPY index.php glassbox.php main.c critical.c build.sh build.flags /var/www/html/

WORKDIR /var/www/html
# The flag lives OUTSIDE the web root, so apache never serves it; only a shell
# spawned by the exploit (which runs with its cwd here) can `cat flag.txt`. The
# challenge itself never prints it.
RUN cp critical.c critical.orig.c \
    && cp build.flags build.orig.flags \
    && bash build.sh \
    && mkdir -p /var/challenge \
    && printf 'R0pP4stTh3NX\n' > /var/challenge/flag.txt \
    && chmod 755 /var/challenge \
    && chmod 644 /var/challenge/flag.txt \
    && chown -R www-data:www-data /var/www/html

CMD ["apache2-foreground"]
