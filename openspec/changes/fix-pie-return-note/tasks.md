## 1. Implementation

- [x] 1.1 In `platform/native/native-run.php`, in `nrun_stack_table_live()`'s
  saved-return-address note branch (`$off === $rbpToBuf + 8`), stop passing the
  pristine `$bVal` to `nrun_resolve_addr`: the unchanged case states that execution
  returns to the caller normally; the changed case shows `$bVal` as raw bytes and
  resolves only `$aVal` via `$resolveRole`.
- [x] 1.2 Add a code comment recording why the pristine value must not be resolved
  (PIE relocates the real return, so static resolution misreads it as unmapped/faulting).

## 2. Verification

- [x] 2.1 Build a PIE binary (`-fpie -pie`) from `ret2win`'s source, capture the frame
  with `gdb-dump.py`, and confirm the pristine saved return is a relocated address
  absent from the static symbols (the pre-fix trigger).
- [x] 2.2 Confirm the rendered saved-return note: unchanged → "returns to the caller
  normally" (no "faults", no static resolution of the pristine value); overwrote →
  win() → resolves the new value to `win()`; overwrote → garbage → keeps the
  "faults when it returns here" clause on the new value.
- [x] 2.3 Confirm the fix reaches both rungs (shared function) and that `ret2libc`'s
  changed-branch gadget naming via `annotations` is unaffected.

## 3. Spec sync

- [x] 3.1 `openspec validate fix-pie-return-note --strict` passes.
- [x] 3.2 Archive the change so the `native-binary-challenges` main spec picks up the
  modified requirement.
