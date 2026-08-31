## Context

See proposal.md — Why. The relevant code today:

- `nrun_stack_table($bin, $payload, $bufSize, $opts)` (shared, `platform/native/`)
  already renders the learner's real bytes per 8-byte slot, classifies slots
  (`buf`/`canary`/`rbp`/`ret`/`above`), and resolves the **return** slot's value via
  `nrun_resolve_addr`. Slots above the return address are labelled only "stack above
  the frame". It also already extends `$total` to `ceil($len/8)*8`, so it can render
  a payload of any length.
- `nrun_resolve_addr($bin, $hex)` maps a value to the FUNC symbol it lands in (or
  `.text`, or "not mapped … faults when it returns here"). It assumes the value is a
  **return target**, which is wrong for a chain's argument slots (e.g. the `"/bin/sh"`
  pointer, which is popped into RDI).
- ret2libc's `index.php` calls the table with `substr($payload, 0, 64)` (truncating)
  and already computes the ingredient addresses `$sysAddr` / `$binshAddr` / `$popAddr`
  (via `nrun_symbol_addr` / `nrun_string_addr` / `nrun_gadget_addr`).

## Goals / Non-Goals

**Goals:** interpret the learner's submitted chain at the Hints level — full bytes
with the read boundary, per-link role resolution, and an alignment verdict — reusing
the shared table where sensible and keeping ret2win unaffected.

**Non-Goals:** a general ROP emulator (no register/stack simulation of arbitrary
gadgets); resolving chains for other rungs; changing the exploit, binary, or flag.

## Decisions

**A — Full bytes + read boundary via an opt-in `readLimit`.** ret2libc passes the
full `$payload` plus `'readLimit' => 64`. `nrun_stack_table` adds a slot class for
offsets `>= readLimit`: rendered as the learner's real bytes but labelled "fed to the
spawned shell (stdin), not on the stack", visually separated from the on-stack slots.
Omitting `readLimit` (ret2win) keeps today's behavior. The read length is
`sizeof(struct msg)` = 64; it is a constant of the challenge, so index.php passes the
literal 64 (kept next to the struct's definition in a comment).

**B — Resolve above-frame slots + a caller annotation map.** `nrun_stack_table` gains
`'annotations' => [ '0xADDR' => 'role text', … ]` and resolves `above` slots the way
it already resolves the `ret` slot. For each filled `ret`/`above` slot it looks up the
value in `annotations` first (challenge-specific role), else falls back to
`nrun_resolve_addr`. ret2libc supplies the map from the addresses it already computes:
the gadget → "a `pop rdi; ret` gadget", `binsh` → "the address of the `\"/bin/sh\"`
string, popped into RDI as `system`'s argument", `system` → "the `system()` call
target", gadget+1 → "a bare `ret` (stack alignment)". Because the map states an
argument slot's real purpose, the misleading "faults when it returns here" no longer
appears for the `/bin/sh` pointer. Keeping the ingredient knowledge in index.php (not
the shared helper) preserves the helper's generality; the map is optional, so ret2win
is unchanged.

**C — Alignment check by a deterministic rule (no gdb).** Derivation: `vuln` is entered
via `call`, so at its `ret` the return-slot address `R` has `R % 16 == 8` (entry RSP
%16==8 → after `push rbp`, `rbp%16==0` → return slot `rbp+8` %16==8), independent of
buffer size or canary. Walking the chain, `system` is entered with
`RSP = (system_slot_address) + 8`, and `system_slot_address = R + (sys_off − retOff)`.
So `RSP_at_system % 16 == (sys_off − retOff) % 16`, and glibc's `movaps` needs that to
be `8`. Rule: **`(sys_off − retOff) % 16 == 8`**. Verified empirically on the shipped
binary — good chain `system@48`: `(48−24)%16 = 8` (works); no-bare-`ret` chain
`system@40`: `0` (SIGSEGV in `system`). index.php finds `sys_off` by scanning the
submitted 8-byte words for `$sysAddr`, uses the `retOff` the table already computes
(`bufSize + canary + 8`), and shows the verdict + the "prepend a bare `ret`" fix when
it fails. Only meaningful without a canary (a canary aborts before the chain runs), so
the check is gated on the chain actually reaching `system`.

**D — A "Chain" tab (Hints).** A new `chain` debug tab (added to the level-1 tab set)
renders the submitted chain as a table: each link's offset, value, resolved role
(same map as B), and a plain-language effect, followed by the alignment verdict (C).
Built entirely in index.php from the submitted bytes + the ingredient map; empty
before a payload is submitted, with a one-line "send a chain to see it interpreted".

## Risks / Trade-offs

- **Annotation map must track the ingredients** → index.php derives it from the same
  `$sysAddr`/`$binshAddr`/`$popAddr` it already computes, so it cannot drift from what
  the ROP-ingredients panel shows.
- **The alignment rule assumes the standard `-O0` frame** → the derivation only relies
  on `R%16==8` (true for any `call`-entered function) and the `retOff` the table
  computes, so it holds across buffer-size and canary changes; it is surfaced only for
  chains that actually place `system`, and a canaried build aborts earlier anyway.
- **`nrun_resolve_addr`'s return-centric wording still shows for un-annotated slots**
  → acceptable: the challenge's own links are annotated; a stray unknown value falling
  back to "faults when it returns here" is then actually correct (it is a bad return
  target).
- **Stale podman COPY of native-run.php** → rebuild the native base `--no-cache`.

## Migration Plan

1. Land the `native-run.php` helper changes, then ret2libc `index.php`.
2. Rebuild: `podman build --no-cache -t glassbox-native ./platform/native/`, then
   `podman build -t glassbox-ret2libc ./challenges/binary/ret2libc/`.
3. Verify by hand: the winning chain shows full bytes with the 64-byte boundary
   marked; each link resolves to its role; the Chain tab lists the steps; a
   no-bare-`ret` chain is flagged as misaligned and, once corrected, is not; ret2win's
   stack view is unchanged.
4. Rollback: revert and rebuild the native base + ret2libc; ret2win unaffected.
