# glassbox-native, the native (C / binary) challenge family.
# Builds FROM the harness and adds everything the binary challenges need: the C
# toolchain (gcc + binutils), a C-mode CodeMirror editor bundle + read-only view,
# live C linting (lint.php), and the shared runner/introspection helpers
# (native-run.php). No gdb, no ptrace; exploitation happens against the downloadable
# binary locally, and the container only needs to build, run, and introspect
# statically.
ARG BASE_IMAGE=glassbox-harness

FROM node:alpine AS build
WORKDIR /build
COPY editor/package.json .
RUN npm install
COPY editor/cm-init.js editor/cm-c-view.js editor/linters.js ./
RUN ./node_modules/.bin/esbuild cm-init.js --bundle --format=iife --outfile=codemirror-bundle.js && \
    ./node_modules/.bin/esbuild cm-c-view.js --bundle --format=iife --outfile=codemirror-c-view.js

FROM ${BASE_IMAGE}

RUN apt-get update \
    && apt-get install -y --no-install-recommends gcc binutils libc6-dev \
    && rm -rf /var/lib/apt/lists/*

COPY nbuild /usr/local/bin/nbuild
RUN chmod +x /usr/local/bin/nbuild

COPY --from=build /build/codemirror-bundle.js /var/www/html/codemirror-bundle.js
COPY --from=build /build/codemirror-c-view.js /var/www/html/codemirror-c-view.js
COPY lint.php native-run.php /var/www/html/
