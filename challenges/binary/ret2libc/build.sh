#!/bin/bash
# Rebuild the ret2libc binary from critical.c (via main.c) + the current build.flags.
# Delegates to the family's nbuild helper: allowlist the learner's flags, compile to
# a temp file, and atomically swap only on success (a failed compile never bricks).
# `-- -static` is an author-fixed flag (not learner-editable): static linking is what
# makes the ROP ingredients — a `pop rdi; ret` gadget, the "/bin/sh" string, and
# system — arise naturally in the binary, so the learner must not be able to drop it.
exec nbuild ret2libc main.c -- -static
