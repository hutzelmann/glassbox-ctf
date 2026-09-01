## Why

`ret2libc`'s vocabulary leaks into `ret2win`. In the payload-derived stack table
every 8-byte word above the saved return address that the learner's input fully
covers is labelled **"chain link"** — unconditionally, with no check that the
challenge actually declared a chain. `ret2win` passes no `annotations`, so a
learner on rung 1 who sends a long input at **Hints** gets rows naming a concept
(`ROP` chaining) that rung never introduces and does not need.

The same rows also print consequence text written for a different slot. The
address resolver's strings assume "the CPU returns to this address"; applied to a
word *above* the return slot they assert a fault that will not happen there:

> chain link
> `0x4141414141414141`: a non-canonical address, so the CPU raises a #GP fault at
> the ret (you control it, but it is not a usable code pointer)

Wrong noun and wrong causation, on the one panel whose whole job is to tell the
learner the truth about their own bytes.

The live (gdb) table already gates the chain wording on `annotations` being
present. The payload-derived table does not. The two drifted because each spells
out the past-frame note inline.

## What Changes

- Gate "chain link" in the payload-derived stack table on the challenge having
  declared `annotations`, mirroring what the live table already does. A rung that
  declares no chain never says "chain link".
- Give the un-annotated past-frame slot honest rung-1 copy: name it as stack above
  the frame, say it is reached only if execution returns again, and still resolve
  its value (so a learner who lands `win()` one slot too high still sees that).
- Make the address resolver slot-aware: an opt-out of the return-target framing
  drops the "#GP fault at the ret" / "faults when it returns here" clauses while
  keeping the identification ("the start of `win()`", "a non-canonical address").
  Existing return-slot callers keep today's wording by default.
- Route both tables' past-frame note through one shared helper so the two cannot
  drift apart again.

No challenge content, payload semantics, offsets, or flags change. This is debug
copy only; both rungs stay solvable exactly as before.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `native-binary-challenges`: chain interpretation is confined to a rung that opts
  into it; a rung that does not SHALL describe past-frame stack slots only in
  vocabulary it has introduced, and slot annotations SHALL NOT assert consequences
  belonging to a different slot.

## Impact

- `platform/native/native-run.php` — `nrun_stack_table()`, `nrun_stack_table_live()`,
  `nrun_resolve_addr()`, plus one new shared note helper.
- Read-only for `challenges/binary/ret2win/index.php` and
  `challenges/binary/ret2libc/index.php`: neither call site changes. `ret2libc`
  keeps its chain labels because it already passes `annotations`.
- `platform/native` base image must be rebuilt for the change to reach a challenge
  container.
