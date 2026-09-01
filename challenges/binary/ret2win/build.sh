#!/bin/bash
# Rebuild the ret2win binary from critical.c (via main.c) + the current build.flags.
# Delegates to the family's nbuild helper: allowlist the flags, compile to a temp
# file, and atomically swap only on success (a failed compile never bricks).
# `--arch x86_64` is author-fixed: the binary is always x86-64 so its addresses match
# the one shared walkthrough, even on an arm64 host (where it runs under qemu-user).
exec nbuild ret2win main.c --arch x86_64
