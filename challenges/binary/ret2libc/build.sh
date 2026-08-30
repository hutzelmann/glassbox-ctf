#!/bin/bash
# Rebuild the ret2libc binary from critical.c (via main.c) + the current build.flags.
# Delegates to the family's nbuild helper: allowlist the flags, compile to a temp
# file, and atomically swap only on success (a failed compile never bricks).
exec nbuild ret2libc main.c
