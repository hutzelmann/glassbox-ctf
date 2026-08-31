# Design: live stack-frame capture

## Context

`native-run.php` introspects the binary statically (objdump / nm / readelf /
checksec) and reads `/proc/<pid>/maps` for the memory map (same-uid, no ptrace).
The stack table (`nrun_stack_table`) is payload-derived: it renders the submitted
bytes over a modeled frame and shows `··` for anything the payload does not cover.
Real runtime values (the random canary, the saved RBP, the live saved return
address, uninitialized buffer contents) are never read. This change adds the one
thing static introspection cannot provide: the actual contents of the frame while
the binary runs.

## Decisions

### D1 - Vehicle: gdb batch, not a ptrace helper

Reading real stack memory requires tracing the running process. Both realistic
options (gdb, a hand-rolled ptrace helper) go through `ptrace` under the hood, so
the capability story is identical: a parent tracing its own direct child via
`PTRACE_TRACEME` is permitted under the default podman/docker seccomp profile and
yama `ptrace_scope`, with no added capability. (The earlier `--cap-add=SYS_PTRACE`
advice was phantom, it documented a feature that did not exist, and was removed.)

Chosen: **gdb in batch mode driven by a gdb-Python script.** It needs zero
hand-rolled breakpoint/single-step C, reads memory and registers robustly, is
symbol-aware, and the dump script doubles as a teachable artifact that mirrors the
`solution.md` pwndbg/gef section. Cost: the `gdb` install (~tens of MB) on the
native image, accepted, the native image is already the heavy family and the
capture is off the critical path.

Rejected: the ptrace helper (leaner, but ~150 lines of fiddly C we own for no
robustness gain here) and a self-instrumenting second build (capturing the real
canary and saved RBP at the `ret` from pure C needs inline asm, and it would mean
maintaining a clean downloadable binary plus a dirty introspection binary).

The `open-tooling-policy` change lifts the prior "No gdb" ban that this decision
depends on.

### D2 - Two capture points inside `vuln()`

- **BP-A = the `call read` instruction.** The frame is fully built (real canary in
  `-0x8(rbp)`, real saved RBP pushed, real saved return address from main's `call
  vuln`) and `buf` still holds uninitialized garbage. This is the pristine frame.
- **BP-B = the instruction immediately after that call.** `read()` has returned, so
  the payload now sits over `buf` -> saved RBP -> canary -> saved return address.
  Placing BP-B before the epilogue's canary check captures the overwritten canary
  even when the check is about to abort.

Both addresses are located from `objdump -d` of `vuln` (the `call ...<read...>`
line and the address of the following line), the same parsing style already in
`native-run.php`. Under `-O0 -g` (the family default) the `call read` is an
unambiguous single instruction, so the two addresses are stable across both rungs.

A single `gdb` run stops at both, dumps the frame window at each, then continues to
exit so the run's real outcome (reached `win()` / segfault / canary abort) is known.

### D3 - What gets dumped

At each breakpoint the dumper reads a window from `buf` (RSP-relative, derived from
the frame size) through the saved return address plus a slot or two above, and
records `$rsp`, `$rbp`, and, when the binary has a canary, the canary slot value.
Output is JSON on gdb stdout: the two windows as raw bytes (hex), plus resolved
`rbp`/`ret`/`canary`. PHP parses it; on any parse/spawn failure the whole capture
is treated as unavailable (D5).

Determinism: the family default is `-no-pie`, so the gdb run reproduces the result
run exactly. With PIE enabled (a learner experiment) addresses vary per run by
design; the frame *structure* still holds and the before/after still teaches, noted
in the panel copy.

### D4 - Rendering: before | after in the same table, not a second tab

The existing single stack table gains two real column groups shown side by side,
**before (pristine)** and **after (clobbered)**, in the same rows, so each slot's
change reads horizontally with no click, by comparing the two columns. The bytes in
each cell never wrap (a mid-word wrap looked broken), and the note column names
which slots the payload overwrote. No background highlight is used: a `<mark>` box
on the multi-line before/after cells read as visual noise, and the column-to-column
diff plus the notes already make the change obvious. The saved-return-address value
is resolved to its symbol/region (`nrun_resolve_addr`) on both sides, so the learner
sees `main+0x..` become `win()` / a gadget / a fault. This finally fills the
`canaryVal` slot the renderer already accepts.

Detail scales with the debug dial inside the one table:
- **Hints (level 1):** today's payload-derived model column. No capture, no gdb.
- **Debug (level 2):** the real before/after columns when capture succeeds.

Pure pico, no custom CSS (only inline `white-space:nowrap` / `overflow-x:auto`, as
the existing challenge pages already use); the wide table sits in an
`overflow-x:auto` container. Rejected: a separate "Live frame" tab (a click between
before and after defeats the side-by-side comparison) and an SVG diagram (past the
simplest layer).

### D5 - Best-effort, off the critical path

Live capture runs only when the debug level is Debug **and** a payload was
submitted (a second, debug-only execution distinct from the result run). On amd64
(CI and the published, amd64-pinned images) it works; on an amd64 image under arm64
emulation, or a sandbox that blocks the trace, the capture is unavailable, the
table falls back to the payload-derived model with a one-line note, and the
challenge is unaffected. Nothing in the normal flow, README, or solution requires
the capture.

### D6 - Revealing the canary

Debug deliberately reveals internals; the real canary is shown freely, with at most
a short note that a real attacker needs a leak primitive to learn it. This does not
trivialize the ladder: `ret2win`'s intended path never touches the canary (canary
*off* is the default; turning it *on* is the fix that defeats the exploit), and a
future canary-leak rung obtains the value through the bug, not through debug.

## Risks

- **Trace blocked in the sandbox (the phantom-cap scar).** Mitigated by D5
  (best-effort + fallback) and de-risked first by a spike: build the native image
  with `gdb` and confirm `gdb -batch` can trace the child and read regs/memory
  unprivileged inside the container. If it cannot, the fallback path already keeps
  everything working, and admitting a documented capability (with pros/cons per the
  open-tooling policy) or the ptrace helper are the alternatives.
- **BP-A/BP-B mislocated if `vuln` is compiled unusually.** Bounded: the family
  default is `-O0 -g`, and capture failure degrades to the model (D5).
- **Latency.** One extra gdb-driven run per submit at the Debug level only; the run
  is already `timeout`-bounded. Hints and plain solving are untouched.
