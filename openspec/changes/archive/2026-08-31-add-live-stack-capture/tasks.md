## 1. De-risk the trace (spike first)

- [x] 1.1 Build `glassbox-native` with `gdb` added and confirm, inside the running
  container as `www-data`, that `gdb -batch -ex 'break vuln' -ex run -ex 'x/8gx
  $rsp' ./ret2win < payload` reads registers and stack memory unprivileged (no
  added capability). If it fails, stop and reassess (documented capability vs the
  ptrace helper) before building the feature; the fallback path (task 3) keeps
  challenges working regardless.

## 2. Native image carries gdb

- [x] 2.1 `platform/native/Dockerfile`: `apt-get install gdb` (and its needed
  runtime), and rewrite the "No gdb, no ptrace" header comment to state gdb ships
  for debug-only introspection.
- [x] 2.2 Add `platform/native/gdb-dump.py`: a gdb-Python dumper. Take the two
  breakpoint addresses and the frame window size as args/env, break at both, dump
  each frame window (raw bytes as hex) plus `$rsp`/`$rbp`/canary, print one JSON
  object, and exit cleanly (emit an explicit "unavailable" JSON on any internal
  failure). Copy it into the image.

## 3. Capture + render in native-run.php

- [x] 3.1 `nrun_vuln_bp_addrs($bin)`: locate BP-A (the `call read` in `vuln`) and
  BP-B (the following instruction) from `objdump -d`; return both or null.
- [x] 3.2 `nrun_gdb_frame($bin, $payload, $bufSize)`: write the payload to a temp
  file, run `gdb -batch -nx -x gdb-dump.py` on the binary with the payload on
  stdin under the existing `timeout`/`ulimit` wrapper, parse the JSON, and return
  a normalized `['before' => ..., 'after' => ..., 'rbp'/'ret'/'canary' => ...]` or
  null when unavailable.
- [x] 3.3 Extend `nrun_stack_table` (or a sibling renderer) to accept the captured
  before/after and render them as side-by-side column groups in the same table:
  real bytes/value per slot on each side, `<mark>` only on slots that changed,
  saved-return-address resolved via `nrun_resolve_addr` on both sides, and the
  canary slot populated from the real value. When no capture is passed, behave
  exactly as today (the payload-derived model).

## 4. Wire both rungs

- [x] 4.1 `challenges/binary/ret2win/index.php`: at level 2 with a submitted
  payload, call `nrun_gdb_frame` and pass the result into the stack table; on null,
  render the model with a one-line "live capture unavailable here" note. Keep the
  level-1 model view unchanged.
- [x] 4.2 `challenges/binary/ret2libc/index.php`: same wiring through the shared
  helper.
- [x] 4.3 Promote the `checksec` and Disassembly tabs/panels from Debug (level 2)
  to Hints (level 1) in both rungs; keep symbols/gadgets, memory map, program
  source, and the live before/after capture at level 2. Update `AGENTS.md`'s
  level-split prose to match.
- [x] 4.4 Derive the buffer size for the stack-view labels from the binary's DWARF
  (`nrun_buf_size`) instead of hard-coding it, so the labels stay correct when a
  learner's fix edits the buffer (for example enlarging it). Fall back to the
  shipped size when DWARF is absent. Wire both rungs' `$BUFSIZE` to it.

## 5. Verify

- [x] 5.1 Build the base chain and each rung; on amd64, submit a canary-enabled
  `ret2win` overflow at Debug and confirm the before column shows the real random
  canary and the after column shows it clobbered, with the abort outcome.
- [x] 5.2 Confirm the before/after appear side by side in one table (no extra tab),
  changed slots marked, saved-return-address resolved on both sides.
- [x] 5.3 Confirm graceful fallback: with capture forced unavailable, the table
  still renders the model with the note and the rung is still solvable.
- [x] 5.4 Confirm level 0/1 and plain solving are unchanged and pay no gdb cost.
- [x] 5.5 `openspec validate add-live-stack-capture --strict` passes.
