## 1. Platform: shared stack-table helper

- [x] 1.1 `platform/native/native-run.php` `nrun_stack_table`: add an opt-in
  `readLimit` — slots at offset `>= readLimit` render the learner's real bytes but are
  classified/labelled as "fed to the spawned shell (stdin), not on the stack", visually
  separated from on-stack slots. Absent `readLimit` = today's behavior (ret2win).
- [x] 1.2 `nrun_stack_table`: resolve `above`-frame filled slots (not just `ret`), and
  accept an optional `annotations` map (`['0xADDR' => 'role text']`) consulted before
  `nrun_resolve_addr` for `ret`/`above` slots, so a caller can name a slot's role and
  an argument slot is not mislabelled as a return target.

## 2. Challenge: ret2libc chain inspection

- [x] 2.1 `challenges/binary/ret2libc/index.php`: pass the full `$payload` (drop the
  `substr(...,0,64)`) plus `'readLimit' => 64` to `nrun_stack_table`; update the
  hexdump/stack copy to reflect the boundary.
- [x] 2.2 `index.php`: build the ingredient annotation map from the already-computed
  `$sysAddr` / `$binshAddr` / `$popAddr` (and gadget+1 = bare `ret`) and pass it as
  `annotations`, so the stack table labels each chain link's role.
- [x] 2.3 `index.php`: compute the alignment verdict — find `sys_off` by scanning the
  submitted 8-byte words for `$sysAddr`, apply `(sys_off − retOff) % 16 == 8`, and when
  it fails show the movaps-fault explanation + "prepend a bare `ret`"; gate on the
  chain actually placing `system`. Show it at Hints level after a run.
- [x] 2.4 `index.php`: add a level-1 "Chain" debug tab that lists the submitted chain
  step by step (offset, value, resolved role, effect) plus the alignment verdict; show
  a one-line placeholder before any payload is submitted.
- [x] 2.5 `native-run.php` `nrun_stack_table_live` + `index.php`: apply the same
  annotation map to the LIVE Debug before/after frame, so the overlapping above-frame
  rows resolve each chain link to its role (gadget / argument / call target) instead of
  a generic "above the frame". (The annotation had only reached the payload-derived
  model table.) ret2win passes no map, so its live table is unchanged.
- [x] 2.6 `native-run.php` `nrun_run`: strip `timeout`'s own meta-lines (e.g. "the
  monitored command dumped core") from the captured stderr — a normal ret2libc segfault
  after the shell exits made even a winning run show a scary error; the crash/timeout
  outcome is still reported from the exit status. General runner cleanup (helps ret2win
  losing attempts too).

## 3. Build and verify (manual; no test suite)

- [x] 3.1 Rebuild: `podman build --no-cache -t glassbox-native ./platform/native/`,
  then `podman build -t glassbox-ret2libc ./challenges/binary/ret2libc/`.
- [x] 3.2 Winning chain: Your-bytes shows all submitted bytes with the 64-byte boundary
  marked; each link resolves to its role (bare ret / pop rdi;ret / → &"/bin/sh" /
  system); the `/bin/sh` slot is NOT labelled "faults when it returns here".
- [x] 3.3 Chain tab lists the steps + alignment verdict; a no-bare-`ret` chain is
  flagged misaligned (movaps) and, once the bare `ret` is added, is not.
- [x] 3.4 ret2win's stack view is unchanged (no `readLimit`/annotations passed).
- [x] 3.5 `openspec validate ret2libc-chain-inspection --strict` passes.
