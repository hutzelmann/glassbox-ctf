## Context

See proposal.md — Why.

The two stack views live in `platform/native/native-run.php`, shared by both binary
rungs:

- `nrun_stack_table()` — payload-derived. Shown at **Hints**, and at **Debug** when
  the live capture fails. Region per word is inferred from `$bufSize` / canary
  presence; anything past the return slot gets `$slot = 'above'`.
- `nrun_stack_table_live()` — the gdb before/after frame. Debug only.

Both accept an optional `annotations` map (address → role) and build an identical
`$resolveRole` closure over it. `ret2libc` passes `$chainRoles`; `ret2win` passes
nothing. That map is already the opt-in seam for chain interpretation — the live
table honours it, the payload-derived table ignores it and prints "chain link"
regardless.

The value text comes from `nrun_resolve_addr()`, whose five return strings were
written for one caller (the saved-return-address row). Three are slot-neutral
("the start of `win()`", "inside the program code (.text), but not a named
function", "a null pointer"); two append a return-time consequence ("…so the CPU
raises a #GP fault at the ret…", "…so the CPU faults when it returns here").

## Goals / Non-Goals

**Goals:**

- One code path decides the past-frame note, so the two tables cannot drift again.
- Chain vocabulary reachable only through the `annotations` opt-in.
- Keep address resolution on un-annotated past-frame rows; drop only the
  return-target consequence clause.

**Non-Goals:**

- No change to either challenge's `index.php`, payload handling, offsets, or flags.
- No rewrite of the return-slot copy, the chain read-out, or `ret2libc`'s chain
  roles. The two tables' chain-link markup does converge on one form (the spec
  requires the note be identical in both views); the role text itself is untouched.
- No new debug panel, and no new debug level.

## Decisions

**Gate the shared table, do not fork it per rung.** The alternative the request
raised — give `ret2win` its own table function — was rejected. The ~130 lines of
`nrun_stack_table()` are byte/endianness/canary/`origRet` logic that must stay
identical across rungs; forking it creates two copies of the part that is correct
in order to fix the one branch that is not. The defect is a *missing gate*, not
shared code: the live table proves the same function serves both rungs correctly
once `annotations` is consulted. Splitting would also break the ladder's promise
that rung 2 shows the same frame as rung 1, with more resolved.

**Extract one `nrun_note_above_frame()` helper.** Both tables call it for the
past-frame slot, passing the normalized annotation map and the word's value. It
returns the chain-link label when the address is annotated, and the plain
past-frame note otherwise. This is what actually prevents recurrence — the drift
happened because each table spelled the note out inline. The address-normalizing
snippet duplicated in both `$resolveRole` closures moves in with it.

**Un-annotated past-frame copy names the slot and its reachability.** The row reads
as stack above the frame, reached only if execution returns again, followed by the
resolved value. That is true on both rungs — on `ret2win` `win()` calls `_exit()`
and never returns, so nothing reads these bytes; on `ret2libc` execution does
return again, which is exactly what the annotated rows then show. It plants the
idea the next rung builds on without naming ROP on rung 1.

**Resolve the value even when un-annotated.** Dropping resolution would be the
smaller change but loses a real hint: a learner who writes `win()`'s address one
slot too high currently sees "the start of `win()`" on that row, which is how they
spot the off-by-one. Keep it.

**Give `nrun_resolve_addr()` an opt-out, not new copy.** A third parameter
`$asReturnTarget = true` keeps every existing caller (both return-slot rows, the
`ret2libc` chain read-out fallback at `challenges/binary/ret2libc/index.php:220`)
on today's exact wording; `false` drops the two consequence clauses and keeps the
identification. Alternatives considered: returning a structured pair for the caller
to compose (larger blast radius across five call sites, all of which want the
default), or stripping the clauses unconditionally (would silently weaken the
return-slot row, which is the one place the clause is correct and load-bearing).

**Live table's un-annotated past-frame row converges on the same note.** It
currently says "your bytes ran past the frame (e.g. a ROP chain)" — same
unintroduced term, one level up, and with no value resolution. Routing it through
the helper fixes both. Its `!$changed` case keeps today's "stack above the frame".

## Risks / Trade-offs

- **A `ret2libc` row regresses to the generic note** because its address is absent
  from `$chainRoles` → that row is one the rung genuinely does not recognise, so the
  generic note is the honest label; the fallback is an improvement, not a loss. Verify
  by running the known-good `ret2libc` payload and confirming every chain row still
  reads "chain link".
- **Stale base image hides the change.** `native-run.php` ships in `glassbox-native`;
  a challenge image layers on top. Rebuild `platform/native` (with `--no-cache` if the
  edit does not appear) before judging the result, per AGENTS.md.
- **No test suite.** Verification is manual, in a browser, on both rungs at both debug
  levels, with the gdb capture succeeding and forced to fail. The tasks list spells
  out the exact cases.

## Migration Plan

None — no data, no persisted state, no challenge-facing API. Rebuild
`platform/native` and the two binary challenge images; rollback is reverting the
commit and rebuilding.
