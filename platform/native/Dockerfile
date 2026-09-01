# glassbox-native, the native (C / binary) challenge family.
# Builds FROM the harness and adds everything the binary challenges need: the C
# toolchain (gcc + binutils), a C-mode CodeMirror editor bundle + read-only view,
# live C linting (lint.php), and the shared runner/introspection helpers
# (native-run.php). It also ships gdb, used only by the Debug view to capture the
# real stack frame before and after the overflow (gdb-dump.py); exploitation still
# happens locally against the downloadable binary, and the capture is best-effort
# and never on the critical path (see native-run.php / AGENTS.md open-tooling rule).
#
# A challenge's exploitable binary targets ONE fixed architecture regardless of the
# host, so every learner faces the same binary and the same walkthrough (see AGENTS.md
# and openspec spec native-binary-challenges). To keep that true on a multi-arch
# container this image carries, on BOTH image variants, the x86-64 and aarch64 cross
# compilers (nbuild picks one by the challenge's --arch), qemu-user (runs a foreign-arch
# binary when the host does not match its target), and gdb-multiarch (captures a foreign
# guest's frame over qemu's gdbstub). Shipping both toolchains uniformly, rather than
# gating on TARGETARCH, is deliberate: it lets the host-not-equal-target path be
# self-tested in a plain container on either host, and CI on amd64 runners proves it.
ARG BASE_IMAGE=glassbox-harness

FROM node:alpine AS build
WORKDIR /build
COPY editor/package.json .
RUN npm install
COPY editor/cm-init.js editor/cm-c-view.js editor/cm-theme.js editor/linters.js ./
RUN ./node_modules/.bin/esbuild cm-init.js --bundle --format=iife --outfile=codemirror-bundle.js && \
    ./node_modules/.bin/esbuild cm-c-view.js --bundle --format=iife --outfile=codemirror-c-view.js

FROM ${BASE_IMAGE}

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       gcc binutils libc6-dev gdb \
       gcc-x86-64-linux-gnu gcc-aarch64-linux-gnu \
       libc6-dev-amd64-cross libc6-dev-arm64-cross \
       qemu-user-static gdb-multiarch \
    && rm -rf /var/lib/apt/lists/*

COPY nbuild /usr/local/bin/nbuild
RUN chmod +x /usr/local/bin/nbuild

COPY --from=build /build/codemirror-bundle.js /var/www/html/codemirror-bundle.js
COPY --from=build /build/codemirror-c-view.js /var/www/html/codemirror-c-view.js
COPY lint.php native-run.php gdb-dump.py /var/www/html/
