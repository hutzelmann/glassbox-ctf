## Context

See proposal.md — Why. The two stack views live in `platform/native/native-run.php`,
shared by both binary rungs:

- `nrun_stack_table()` — payload-derived. Uses `nrun_return_addr()` (objdump) for the
  original return, which is a **static** address that resolves correctly under PIE.
- `nrun_stack_table_live()` — the gdb before/after frame (Debug only). Captures the
  **runtime** frame, so under PIE its addresses are relocated to a random base.

`nrun_resolve_addr($bin, $addr)` maps an address against the binary's static symbol
table (`readelf -sW`). It has five return strings; two append a return-time
consequence ("…#GP fault at the ret…", "…so the CPU faults when it returns here"),
and it already takes an `asReturnTarget` opt-out (from the `past-frame-slot-copy`
change) that drops those clauses while keeping identification.

The defect: the live table fed the **pristine captured** saved-return value through
`nrun_resolve_addr` in both the unchanged and the changed branch. Static symbols
equal runtime addresses only for `-no-pie`, so under PIE the real relocated return
into `main()` misses every static symbol and the resolver asserts a fault on a row
that in fact returns normally.

## Goals / Non-Goals

**Goals:**

- The pristine (before) value of the saved-return row is never resolved as a return
  target; it is the slot's original contents.
- An unchanged saved return reads as returning to the caller normally, on both
  `-no-pie` and PIE builds.
- Keep the correct read-out on the value that occupies the slot after the read
  (`win()` / gadget / bad-pointer-faults), which is the actual teaching signal.
- Fix lands identically in both rungs via the shared function.

**Non-Goals:**

- No PIE-aware relocation of resolution. The value that occupies the slot after the
  read is still resolved against static symbols. For the shipped `-no-pie` builds
  that is exact; naming a relocated runtime target correctly would require the
  runtime base and is out of scope here.
- No change to the payload-derived model table, the canary/RBP/above-frame rows,
  offsets, flags, or any challenge source.

## Decisions

**Do not resolve the pristine value — in either branch.** The pristine saved return
is, by construction, the genuine address the CPU would take; its meaning is
structural ("the original saved return"), not something to look up. The unchanged
branch states that plainly and drops the resolver call entirely. The changed branch
shows the pristine value as raw bytes (the "before (pristine)" column already prints
it) and resolves only the new value. This is a single, consistent rule — the
pristine value is data, the landing value is the return target — rather than two
special cases.

Alternative rejected — *resolve the pristine value with `asReturnTarget=false`*: it
would drop the "faults" clause but still print "not mapped in the program" for a
valid relocated return, which is still misleading on an unchanged row. The value's
raw bytes in the before column already convey everything the learner needs.

Alternative rejected — *make `nrun_resolve_addr` PIE-aware*: correct in principle but
needs the runtime load base wired from the gdb capture through to the resolver,
enlarging surface for a copy defect. Deferred as a non-goal; the shipped challenges
are `-no-pie`, and the PIE lane's own lesson (address moved, hardcoded address is
wrong) is already carried by the memory-map panel and the segfault banner.

**Fix the shared function, not per-rung copies.** `nrun_stack_table_live` serves both
rungs; editing the one branch fixes `ret2win` and `ret2libc` together and keeps the
ladder's promise that rung 2 shows the same frame as rung 1. `ret2libc`'s changed
branch keeps `$resolveRole($aVal)`, so its `annotations`-driven gadget naming is
untouched.

## Risks / Trade-offs

- [Lost detail: unchanged `-no-pie` row no longer prints "inside main() (+0x45)"] →
  Minor. The pristine value's raw bytes and little-endian value are still shown in
  the before column; the note gains clarity and correctness in exchange, and the
  user explicitly asked to simplify this note.
- [Changed row under PIE still resolves the learner's hardcoded target statically
  (e.g. "the start of win()") though it faults at runtime] → Pre-existing and out of
  scope; it names the address the learner aimed at, and the segfault banner plus the
  memory-map panel carry the PIE lesson. Recorded as a non-goal above.
