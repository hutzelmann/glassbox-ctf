## Why

The `?debug=1` switch is all-or-nothing: a learner who wants syntax highlighting
and lint feedback on their payload must also accept the exact SQL the server
built, the rows it returned, and the admin's rendered page — which hands them the
answer and ends the challenge. A learner asked for the middle ground: better
tooling and error feedback without the spoiler. The switch conflates two
independent things (an *editing affordance* and an *internals reveal*), and
splitting them costs nothing pedagogically while restoring a usable rung between
"stuck in the dark" and "told the answer".

## What Changes

- **BREAKING**: `?debug=1` now selects the new middle level, not full debug. The
  full view moves to `?debug=2`. Every `?debug=1` reference in the repository's
  documentation is updated in this change.
- The glass-box debug switch becomes a three-level dial, cumulative:
  - **0 — Off**: the challenge exactly as shipped.
  - **1 — Hints**: the learner's input becomes a CodeMirror editor with
    language-appropriate highlighting and linting, and the challenge surfaces the
    error/symptom signals telling the learner *that* and *how* their own attempt
    failed (the real `$db->error`, the admin bot's JS console errors, the blind
    challenge's timing instrument).
  - **2 — Debug**: today's full internals — the assembled `$sql`, returned rows,
    the admin's rendered page source, the raw request as PHP parsed it, and the
    read-only `critical.php` view.
- The author-facing placement rule: a panel belongs at level 1 if it reports how
  the learner's own attempt failed; at level 2 if it discloses server code, the
  assembled query, returned data, or the victim's rendered output.
- The header control changes from a `role="switch"` checkbox to a three-option
  pico `<select>` (Off / Hints / Debug), still writing the sticky `debug` URL
  parameter.
- New shared `platform/harness/debug.php` provides `$debugLevel`, `$debugSuffix`,
  and `debug_switch()`, replacing roughly forty copy-pasted
  `$_GET['debug'] === '1'` tests across nine challenge pages.
- New `platform/php/editor/cm-sql-input.js` bundle: a form-wired MySQL editor for
  SQLi payload fields, deliberately without the unterminated-string linter (an
  unterminated string is the goal of the exercise, not a mistake).
- All CodeMirror bundles move from `querySelector` to `querySelectorAll`, so a
  page with two injectable fields (`sqli-login`) gets an editor on both.
- Challenges that today have no internals to reveal (`xss-light`, `xss-shop`,
  `hello`) gain real level-2 content, so no challenge offers a dead level.
- The root `README.md` features the three-level dial as a headline capability of
  the glass box.

## Capabilities

### New Capabilities

None. This changes how an existing glass-box mechanism behaves; it introduces no
new learner-facing capability area.

### Modified Capabilities

- `challenge-structure`: the glass-box editing contract currently requires each
  page to support "the sticky `?debug=1` switch that reveals server internals".
  That requirement is replaced by a three-level debug contract, with a placement
  rule for which output belongs at which level, a shared harness-provided
  implementation, and the level-1 editor affordance.
- `challenge-docs`: the README requirement currently says the README explains
  "what the Fix editor and `?debug=1` view do". That becomes an obligation to
  describe both levels, and `solution.md` walkthroughs must reference the level
  that actually shows what they describe.

## Impact

**Platform**
- new `platform/harness/debug.php`; `platform/harness/Dockerfile` copies it
- new `platform/php/editor/cm-sql-input.js`; `platform/php/Dockerfile` gains an
  esbuild target and a `COPY`
- `platform/php/editor/cm-init.js`, `cm-html-edit.js`, `cm-sql-edit.js`,
  `cm-php-view.js`: `querySelector` → `querySelectorAll`

**Challenges** — nine pages across seven challenges: `intro/hello/index.php`,
`web/sqli-login/index.php`, `web/sqli-blind/index.php`,
`web/sqli-insert/index.php`, `web/xss-light/index.php`, `web/xss-shop/index.php`,
`web/xss-cookie/index.php`, `web/xss-cookie/search.php`,
`web/xss-cookie/chat.php`. Each requires `debug.php`, renders the new control,
splits its debug output across the two levels, and gains its new level-1 or
level-2 panels.

**Docs** — root `README.md`, eight challenge `README.md`, eight `solution.md`,
`AGENTS.md`, and the two modified specs. The archived
`2026-08-30-restructure-repo` change is left frozen.

**Not affected** — `challenges/intro/runtime-check` (exempt from the glass-box
contract), CI (`docker-publish.yml` auto-discovers and needs no edit), and
`fix.php`, whose editor is unconditional and stays at level 0.
