## 1. Harness: dial re-run (default on, opt-out)

- [x] 1.1 `platform/harness/debug.php` `debug_switch()`: give the `<select>` an id and
  attach its change handler from a sibling `<script>`. On change, find the first
  `<form method="POST">` (case-insensitive) that is NOT marked `data-debug-no-resubmit`
  and has a filled data input (text/search/password/number/url/email/textarea with a
  non-empty trimmed value; ignore submit/button/hidden/file). If found, set its
  `action` to the new level (`./` for 0, `./?debug=N` otherwise) and submit it; else
  fall back to `window.location.replace` GET navigation.

## 2. Platform: stack table no-wrap

- [x] 2.1 `platform/native/native-run.php` `nrun_stack_table`: add
  `style="white-space:nowrap"` to the byte and value `<td>`s (match the live table).

## 3. Challenges: opt-out where unsafe, drop the old opt-in

- [x] 3.1 `challenges/web/sqli-insert/index.php`: add `data-debug-no-resubmit` to the
  **register** form (its INSERT must not be replayed on a level change); leave login
  and logout as-is.
- [x] 3.2 `challenges/binary/ret2libc/index.php` and
  `challenges/binary/ret2win/index.php`: remove the now-redundant
  `data-debug-resubmit="payload_hex"` attribute (default-on covers them).

## 4. Build and verify (manual; no test suite)

- [x] 4.1 Rebuild the base chain `--no-cache` (`glassbox-harness`), then `glassbox-php`,
  `glassbox-native`, then a representative set of challenges: `ret2libc`, `sqli-login`,
  `sqli-insert`.
- [x] 4.2 Binary: submit a chain, switch the dial Challenge↔Hints↔Debug — the result
  persists with one click; the bytes column does not wrap.
- [x] 4.3 `sqli-login`: submit a login, switch the dial — the same login result is shown
  at the new level (no manual resend).
- [x] 4.4 `sqli-insert`: after a **login**, switching the dial re-runs it; after a
  **register**, switching the dial navigates without replaying the INSERT.
- [x] 4.5 Before any submission (any challenge), switching the dial navigates without a
  spurious POST.
- [x] 4.6 `openspec validate sticky-debug-rerun --strict` passes.
