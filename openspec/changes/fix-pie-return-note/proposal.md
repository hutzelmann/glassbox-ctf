## Why

The live gdb before/after stack-frame table (`nrun_stack_table_live`, shared by
`ret2win` and `ret2libc`) runs the **pristine** captured saved-return value through
the static-symbol resolver `nrun_resolve_addr`. That resolver only knows the
binary's static symbol addresses, which equal the runtime addresses **only for a
`-no-pie` binary**. When a learner turns on PIE in the Fix editor's compiler-flags
lane, the real saved return into `main()` is relocated to a random base, so it is
not one of the binary's static symbols and the resolver falls through to its last
branch on the **unchanged** saved-return row:

> the CPU returns here, unchanged: not mapped in the program, so the CPU faults
> when it returns here

That is wrong and self-contradictory: an *unchanged* saved return is the genuine
original return address; when `vuln()` returns to it, execution goes back to
`main()` normally — it does not fault. The one panel whose job is to tell the
learner the truth about their own bytes asserts a fault that will not happen, on
exactly the lane (PIE) the debug view exists to teach. The shipped `-no-pie` build
hides the defect because there static == runtime, so the value resolves to
`inside main()`.

## What Changes

- The live frame table SHALL NOT resolve, or attach return-target consequence text
  to, the **pristine (before)** captured value of the saved-return-address row. An
  unchanged saved-return row states that execution returns to the caller normally.
- The changed saved-return row resolves only the value that now occupies the slot
  (the address actually returned to), keeping today's `win()` / gadget / bad-pointer
  read-out; the pristine value it replaced is shown as raw bytes, not resolved.
- The payload-derived model table (`nrun_stack_table`) is unchanged: it already
  resolves the *static* original return address (`nrun_return_addr`), which is
  correct under PIE, so it never showed the false fault.

No challenge content, payload semantics, offsets, or compiler flags change. This is
debug copy only; both rungs stay solvable exactly as before.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `native-binary-challenges`: the debug stack-frame view SHALL describe the pristine
  captured value of a slot only as its original contents (an unchanged saved return
  returns to the caller normally), and SHALL apply address resolution and
  return-target consequence text only to the value that occupies the slot after the
  read — never to the pristine value, so a relocated (PIE) original return is not
  misread as a faulting, unmapped pointer.

## Impact

- `platform/native/native-run.php` — `nrun_stack_table_live()`, saved-return-address
  note branch only.
- Read-only for `challenges/binary/ret2win/index.php` and
  `challenges/binary/ret2libc/index.php` (both call the shared function; the fix
  reaches both identically).
