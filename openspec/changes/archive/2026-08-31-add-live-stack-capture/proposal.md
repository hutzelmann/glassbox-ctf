## Why

The binary rungs already render a stack-frame table, but it is a **model**, not
memory: `nrun_stack_table` lays the learner's submitted bytes onto a statically
derived frame. Slots the payload does not reach show `··`; the real saved frame
pointer is never shown; the random stack canary is never read (the renderer even
accepts a `canaryVal` the challenges pass as `null`); the "original" return
address comes from `objdump`, not the live stack.

So the learner never sees the thing the lesson is about: the *actual* bytes on the
stack. A canary is described as "random, checked before return" but never shown as
a real per-run value being clobbered. The highest-value moment in the whole ladder,
watching a real random canary get overwritten and the program abort, is missing
because nothing reads the running process's memory.

This change captures the **real** stack frame from the running binary with `gdb`
and shows it **before and after** the overflow, side by side, at the Debug level.

## What Changes

- Add **`gdb`** to the `platform/native/` image (fully offline, installed at build
  time) and update that Dockerfile's comment. Enabled by the `open-tooling-policy`
  change, which lifts the prior `gdb` ban.
- Add a **live stack-frame capture** to `native-run.php`: run the binary under
  `gdb` in batch mode with a small gdb-Python dumper, breakpoint at two
  statically located points inside `vuln()`, the `call read` (pristine frame) and
  the instruction after it (payload-clobbered frame), and emit both frame windows
  plus the saved RBP, saved return address, and stack canary as JSON. Same
  `timeout`/`ulimit` sandbox as the normal run.
- Render the capture as **before | after columns in the existing single stack
  table**, not a second tab: the real saved return address, real saved frame
  pointer, real canary, and real (uninitialized) buffer bytes on the before side,
  the payload's bytes over them on the after side, with the changed slots marked so
  the clobber reads across in one glance. The saved-return-address value is
  resolved to its symbol or region on both sides.
- **Detail scales with the dial in the same table.** At **Hints** the table stays
  today's payload-derived model (no capture, no `gdb` needed). At **Debug** the
  same table shows the real before/after when capture succeeds. Live capture runs
  only at the Debug level and only after a payload is submitted, so plain solving
  and the Hints view pay nothing.
- **Best-effort with graceful fallback.** When live capture is unavailable (for
  example an amd64 image under arm64 emulation, or a sandbox that blocks the trace),
  the table falls back to the payload-derived model and says so. Every rung stays
  fully solvable without the capture; it is never on the critical path.
- Apply to **both** rungs, `ret2win` (canary-centric) and `ret2libc` (the ROP chain
  laid over the real frame), through the shared `native-run.php` helper.
- **Surface `checksec` and the disassembly at Hints (level 1)**, alongside the
  your-bytes stack table, so the Hints view has enough static context to orient
  (the spec's debug requirement already lists both under `?debug=1`). The deeper
  target internals (symbols/gadget addresses, memory map, program source) and the
  live before/after capture stay at Debug (level 2). `AGENTS.md`'s level-split prose
  is updated to match.
- Update the `native-binary-challenges` spec: the Debug view captures the real
  frame before and after the overflow.

## Impact

- Affected specs: `native-binary-challenges` (one added requirement + scenarios).
- Affected code: `platform/native/Dockerfile` (add `gdb`, comment), a new gdb
  dumper script under `platform/native/`, `platform/native/native-run.php` (capture
  + before/after rendering), `challenges/binary/ret2win/index.php` and
  `challenges/binary/ret2libc/index.php` (wire the before/after table).
- Depends on: `open-tooling-policy` (lifts the `gdb`/`ptrace` ban and adds `gdb` to
  the native family description in `AGENTS.md`).
- No change to the shipped/downloadable binary, the payload-runner flow, CI, or the
  challenge solutions. Image size grows by the `gdb` install (accepted; the native
  image is already the heavy one and the capture is off the critical path).
