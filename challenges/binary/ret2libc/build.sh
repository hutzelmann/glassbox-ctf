#!/bin/bash
# Rebuild the ret2libc binary from critical.c (via main.c) + the current build.flags.
# Delegates to the family's nbuild helper: allowlist the learner's flags, compile to
# a temp file, and atomically swap only on success (a failed compile never bricks).
# `-- -static` is an author-fixed flag (not learner-editable): static linking is what
# makes the ROP ingredients — a `pop rdi; ret` gadget, the "/bin/sh" string, and
# system — arise naturally in the binary, so the learner must not be able to drop it.
# `--arch x86_64` is likewise author-fixed: the binary is always x86-64 so its gadget
# and symbol addresses match the one shared walkthrough, even on an arm64 host (where
# it runs under qemu-user).
exec nbuild ret2libc main.c --arch x86_64 -- -static
