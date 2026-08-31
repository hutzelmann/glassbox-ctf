## Context

See proposal.md — Why. `debug_switch()` in `platform/harness/debug.php` renders the
dial as a `<select>` whose inline `onchange` does `window.location.replace('?debug=N')`
— a GET that drops any prior POST. The binary payload form already carries the level in
its `action` (`./?debug=N` via `$debugSuffix`) and its field is remembered across
navigation by `remember-form-input.js`, but nothing re-runs it, so the result is lost.
`nrun_stack_table` (the payload-derived table) emits byte/value cells without a
`white-space` rule; the live table (`nrun_stack_table_live`) already uses
`white-space:nowrap`.

## Goals / Non-Goals

**Goals:** one-click level switch that preserves the current result on pages that opt
in; stop the stack table's hex from wrapping. Keep the change safe for every existing
challenge.

**Non-Goals:** opting web challenges in; caching results server-side; any change to
what each level shows.

## Decisions

**Opt-out re-run, default on, with generic form detection.** The dial re-runs by
default on every challenge, and picks the form to re-submit generically so no per-
challenge opt-in is needed: it takes the first `<form method="POST">` that is not
marked `data-debug-no-resubmit` and has at least one filled data input
(text/search/password/number/url/email/textarea with a non-empty trimmed value —
buttons, hidden and file inputs do not count). It sets that form's `action` to the new
level and submits it; if no such form exists it falls back to GET navigation. The
filled-input test is what makes "default on" safe without a spurious POST: before any
submission the fields are empty, so the dial just navigates. Across a normal challenge
this resolves to the single login/payload form after it has been used.

Only `sqli-insert` needs an explicit opt-out: it has three POST forms — login (a
`SELECT`, safe to replay), register (an `INSERT`, not safe), and logout (a button-only
form, never a candidate since it has no filled data input). Its register form gets
`data-debug-no-resubmit`, so after a register the dial navigates (the register result
is re-entered rather than replayed) while login re-runs normally. GET forms
(`xss-light`) are untouched: the dial's GET branch already carries their query params
to the new level. The binary forms drop the old opt-in attribute — default-on covers
them.

**Wire the dial from a script, not a long inline handler.** The resubmit logic is more
than fits cleanly in an inline `onchange` attribute (quoting a `[name="…"]` selector
inside a double-quoted attribute is fragile). `debug_switch()` gives the `<select>` an
id and attaches the handler from a sibling `<script>`. This is inline JS like the
dial's current `onchange`, so it needs no new asset and no CSP change.

**nowrap by style attribute.** Add `style="white-space:nowrap"` to the payload-derived
table's byte and value `<td>`s, matching the live table. Purely presentational.

## Risks / Trade-offs

- **A re-POST triggers the browser's "resend form?" prompt on a later manual reload** →
  acceptable and familiar; it only occurs if the learner reloads after a dial-driven
  submit, and the result is still correct. The alternative (GET + server-side result
  cache) is far heavier for a teaching container.
- **Harness change rebuilds the whole image chain** → `debug.php` lives in the harness
  base, so harness → php/native → challenges rebuild; a stale COPY layer would ship the
  old dial, so rebuild the harness `--no-cache`.
- **Default-on could replay an unsafe submit that forgot to opt out** → the only
  non-idempotent web submit is `sqli-insert`'s register (INSERT); it is opted out here,
  and future state-changing forms must add `data-debug-no-resubmit`. The failure mode
  is a replayed submit, so this is called out in the challenge-authoring notes.
- **A form input with a default value re-submits before the learner "used" it** → the
  filled-input test keys on a non-empty value, so a field pre-filled by the challenge
  would count; in practice the challenge forms start empty, and a stray re-POST of an
  empty-intent form only re-renders the same page. Acceptable versus the complexity of
  tracking "was actually submitted" on the client.
- **Multiple filled POST forms on one page** → the dial re-submits the first
  non-opted-out one. Only `sqli-insert` has multiple, and opting its register out
  leaves login as the single candidate, so the ambiguity does not arise in practice.

## Migration Plan

1. Edit `debug.php` (dial script), `native-run.php` (nowrap), and the two binary forms.
2. Rebuild: `podman build --no-cache -t glassbox-harness ./platform/harness/`, then
   `glassbox-php`, `glassbox-native`, then the binary challenges.
3. Verify: submit a chain, switch the dial Hints↔Debug↔Challenge — the result persists
   with one click at each level; the bytes column does not wrap; a web challenge's dial
   still just navigates (no resubmit).
4. Rollback: revert and rebuild the base chain.
