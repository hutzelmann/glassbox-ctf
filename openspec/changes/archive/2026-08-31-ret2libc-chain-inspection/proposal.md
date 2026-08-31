## Why

ret2libc is a harder rung than ret2win: the learner builds a multi-link ROP chain,
not a single return address, and must respect a 16-byte stack-alignment rule and a
64-byte read boundary (bytes past it feed the spawned shell, not the stack). The
current glass-box view was written for the single-address ret2win case and
under-serves this:

- the "Your bytes" stack table is truncated to the first 64 bytes, so it hides the
  shell-feed tail the learner actually typed;
- the chain links above the saved return address are labelled only "stack above the
  frame" — unresolved — and the one resolver that does run mislabels the `"/bin/sh"`
  pointer as "faults when it returns here", which is wrong: that slot is popped into
  RDI as an argument, never returned to;
- nothing tells the learner why an otherwise-correct chain segfaults inside `system`
  (the movaps 16-byte-alignment bug — the single most common ret2libc mistake).

## What Changes

- **"Your bytes" shows the real, full bytes.** Pass the whole submitted payload to
  the stack table and mark the 64-byte `read()` boundary: bytes below it land on the
  stack, bytes above it feed the spawned shell's stdin.
- **Chain annotation.** Resolve every filled slot from the saved return address
  upward and label its role — `bare ret (alignment)`, `pop rdi; ret gadget`,
  `→ &"/bin/sh"`, `system()` — instead of "stack above the frame". This makes the
  table chain-aware and corrects the misleading "faults when it returns here" note on
  argument slots.
- **Alignment check.** After a run, when the chain calls `system`, compute whether it
  is reached 16-byte aligned using the deterministic rule
  `(system_slot_offset − retOff) % 16 == 8`; if not, tell the learner the chain will
  fault in `system`'s `movaps` and to prepend a bare `ret`.
- **A dedicated "Chain" tab.** Present the annotation as a step-by-step ROP read-out
  (slot → value → role → effect) for the submitted chain, cleaner than reading it off
  the raw frame, plus the alignment verdict.

All three interpret the learner's *own* submitted bytes, so they belong at the
Hints (`?debug=1`) level, consistent with the debug dial's symptom-vs-cause rule.

## Capabilities

### New Capabilities

<!-- none: this refines the existing native-binary-challenges debug-view behavior -->

### Modified Capabilities

- `native-binary-challenges`: the debug view interprets a submitted ROP chain — it
  shows the learner's full submitted bytes with the read boundary marked, resolves
  each chain link to its role, and reports whether the chain reaches `system` with the
  required 16-byte stack alignment.

## Impact

- **Challenge** (`challenges/binary/ret2libc/`): `index.php` (full payload + read
  limit to the stack table, ingredient annotations, alignment computation, the new
  Chain tab).
- **Platform** (`platform/native/native-run.php`): `nrun_stack_table` gains an opt-in
  `readLimit` (mark shell-stdin slots) and resolves above-frame slots, with an
  optional caller-supplied annotation map; both are backward-compatible, so ret2win
  is unaffected. Rebuilds the native base image.
- **Out of scope:** ret2win UI changes, and any change to the exploit, the binary, or
  the flag.
