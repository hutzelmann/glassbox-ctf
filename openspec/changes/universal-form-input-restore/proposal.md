## Why

Three challenge pages carry a hand-written `<script>` block that stashes form
values in `sessionStorage` on submit and puts them back on the next load
(`sqli-login`, `sqli-blind`, `xss-shop`). It exists because the pages re-render
their forms empty after a POST and the way back is a fresh GET, so the browser's
own form restoration never applies. Without it the learner retypes their payload
every attempt.

Two problems. First, it is configured field by field, so every new form needs a
new hand-written copy, and the six other form-bearing pages (`sqli-insert`,
`xss-light`, `xss-cookie/chat.php`, `xss-cookie/search.php`, and both binary
rungs) simply never got one. Second, it sits in the page source a learner is
invited to read, next to the code that *is* the lesson, with nothing marking it
as irrelevant housekeeping. That is a confusion generator in a project whose
premise is "read the source and understand what happens".

The per-field configuration has already produced silent bugs. In `xss-shop` at
level 1, the CodeMirror bundle mounts its editor from the still-empty textarea
before the inline restore script runs, and then overwrites the restored value on
submit: the comment restore does nothing, and nothing says so. More broadly, the
level-1 editors only write their document back into the underlying field on
`submit`, so leaving a page any other way, above all by moving the debug dial,
silently discards what the learner typed into the editor.

## What Changes

- Add `platform/harness/remember-form-input.js`: one dependency-free helper that
  remembers every form on the page with no per-field configuration, and whose
  header comment states plainly that it is a comfort feature and not part of any
  challenge.
- Pages include it with a single `<script>` tag in `<head>`, deliberately
  **without** `defer`, so its `DOMContentLoaded` listener is registered before
  the deferred CodeMirror bundles run. This fixes the `xss-shop` bug.
- Delete the three inline restore scripts.
- Extend the behaviour to the six form-bearing pages that never had it, including
  `ret2win` and `ret2libc`, whose payload field is the most expensive thing in the
  repo to retype.
- Restoring survives a change of debug level, which becomes a documented feature.
  Field *names* are stable across levels (level 1 swaps an `<input>` for a
  `<textarea>` of the same name), so the stored entry still matches.
- Make the two editor bundles whose textarea sits inside a form
  (`cm-html-edit.js`, `cm-sql-input.js`) write their document back on every
  change rather than only on submit.
- Refilling a field raises the same `input` or `change` event typing would, so
  page scripts that mirror one field into another stay consistent. The binary
  rungs need this: they derive the readable escape view from the hex payload in
  an inline script that has already run by restore time.
- Drop `value="0"` from the `xss-shop` quantity inputs so "the server left this
  blank" stays a rule with no exceptions. This is a visible change to the
  challenge as shipped at level 0: the quantity boxes start empty instead of
  showing three zeros. Server behaviour is unchanged, because `(int)"" === 0`
  lands on the same "no items selected" branch that all-zeros does today.
- `fix.php` deliberately does not load the helper. Its fields always show the
  file currently on disk, and both of them can legitimately be empty, so a refill
  would leave the editor disagreeing with what is actually running.

## Impact

- New file in the `glassbox-harness` base image, so both families inherit it and
  the binary challenges need no Dockerfile change.
- Nine challenge pages edited (one `<script>` tag each; three also lose their
  inline block).
- Two PHP editor bundles gain a continuous sync.
- `challenge-structure` spec: two requirements modified, one added. The harness
  requirement also gains `debug.php`, which the spec omitted when the debug dial
  landed; that is deliberate, not collateral.
- Docs: `AGENTS.md` and the top-level `README.md`.
- No CI change: no new image, no new build stage.
