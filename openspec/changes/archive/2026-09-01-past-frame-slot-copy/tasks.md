## 1. Slot-aware address resolution

- [x] 1.1 Add a third parameter `bool $asReturnTarget = true` to
  `nrun_resolve_addr()` in `platform/native/native-run.php`. When `false`, the
  non-canonical branch and the not-mapped branch drop their return-time
  consequence clause and keep only the identification; the symbol, `.text` and
  null-pointer branches are unchanged.
- [x] 1.2 Confirm no existing call site passes a third argument, so both
  return-slot rows, `nrun_stack_table()`'s "not reached" row, and the `ret2libc`
  chain read-out at `challenges/binary/ret2libc/index.php:220` keep today's exact
  wording.

## 2. One shared past-frame note

- [x] 2.1 Extract the annotation-map normalization duplicated in
  `nrun_stack_table()` and `nrun_stack_table_live()` into one helper, so an
  address is keyed identically for both tables.
- [x] 2.2 Add `nrun_note_above_frame()`: given the binary, the normalized
  annotation map and a word's value, return the `chain link` label when the value
  is annotated, and otherwise the plain past-frame note — stack above the frame,
  reached only if execution returns again, plus the value resolved with
  `$asReturnTarget = false`.
- [x] 2.3 Keep the caller responsible for the cases the helper does not cover: a
  value that is not fully known, an uncovered slot, and (live table) an unchanged
  slot all keep today's "stack above the frame".

## 3. Route both tables through it

- [x] 3.1 Replace the unconditional `'chain link'` branch in `nrun_stack_table()`
  (the `$slot === 'above' && $covered && $known` case) with a call to the helper.
- [x] 3.2 Replace the `annotations`-gated branch and the
  "your bytes ran past the frame (e.g. a ROP chain)" branch in
  `nrun_stack_table_live()` with a call to the same helper, so the two tables
  produce the same note for the same address.
- [x] 3.3 Update the comments at both call sites that describe the past-frame note
  so they point at the helper instead of restating the copy.

## 4. Verify in the browser

- [x] 4.1 Rebuild `platform/harness` and `platform/native`, then both binary
  challenge images (`--no-cache` on the base if an edit does not take effect).
- [x] 4.2 `ret2win` at Hints, input long enough to fill whole words past the return
  slot: rows past the return address read as stack above the frame with the value
  resolved, no row says chain link / gadget / ROP, and no row claims a fault at the
  `ret`.
- [x] 4.3 `ret2win` at Debug with the live capture succeeding: the past-frame rows
  match 4.2's wording; then force the capture to fail and confirm the
  payload-derived fallback says the same thing.
- [x] 4.4 `ret2win`: put `win()`'s address one slot above the return slot and
  confirm that row still resolves to `win()`, so the off-by-one stays visible.
- [x] 4.5 `ret2libc` at Hints and Debug with the known-good chain payload: every
  chain slot still reads `chain link` with its role, the read-boundary/stdin rows
  and the alignment verdict are unchanged, and the chain read-out is unchanged.
- [x] 4.6 `ret2libc` with a chain containing one unrecognised address: that row
  falls back to the generic past-frame note instead of being called a chain link.

## 5. Land it

- [x] 5.1 `openspec validate past-frame-slot-copy --strict`.
- [x] 5.2 Commit on `main` and archive the change.
