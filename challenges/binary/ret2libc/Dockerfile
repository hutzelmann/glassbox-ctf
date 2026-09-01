ARG BASE_IMAGE=glassbox-native

FROM ${BASE_IMAGE}

# The image publishes for both architectures like every other challenge. The
# exploitable binary is pinned to x86-64 (build.sh: nbuild --arch x86_64), so its
# gadget/symbol addresses match the one shared walkthrough on every host; on an arm64
# host the native family runs it under qemu-user. No per-challenge platform restriction.

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
