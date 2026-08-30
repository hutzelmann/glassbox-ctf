## Context

See `proposal.md` — Why. The relevant current state:

- Every page inlines `isset($_GET['debug']) && $_GET['debug'] === '1'` — roughly
  forty occurrences across nine pages — plus its own copy of the
  `role="switch"` checkbox and its `onchange` URL-rewriting one-liner.
- `$debugSuffix` is computed identically at the top of all nine pages and
  appended to internal links.
- Debug-only CodeMirror bundles are `<script>`-included only when the flag is on.
  The XSS pages additionally swap a plain `<input>` for a
  `<textarea data-codemirror="html-edit">`; the SQLi pages instead emit a
  `data-codemirror="sql-edit"` textarea containing the server's assembled `$sql`.
- `platform/harness/` ships `fix.php` and `pico.min.css`; `platform/php/` ships
  the esbuilt CodeMirror bundles, `lint.php`, `psalm.xml`. Challenge Dockerfiles
  are `FROM ${BASE_IMAGE}` (default `glassbox-php`).
- No test suite. Verification is building the container and clicking through it.

Constraints from `AGENTS.md`: no custom CSS (pico only); prefer the simplest
layer (HTML5 > CSS > JS > server-side); nothing fetched at runtime; challenge
folders stay readable as standalone teaching artifacts.

## Goals / Non-Goals

**Goals:**

- One place that parses the level, formats the sticky suffix, and renders the
  control, so a future fourth level is a platform edit rather than a nine-file
  sweep.
- Keep `$debugSuffix`'s name and role unchanged, so existing link code across all
  nine pages needs no edit for stickiness.
- Keep the level in the URL. A shareable, typeable URL is part of how the
  challenges are taught and how `solution.md` walkthroughs are written.
- Give the SQLi rungs a real level 1, since their existing debug output is
  entirely cause-side.

**Non-Goals:**

- No server-side session or cookie persistence for the level. URL-only, as today.
- No per-panel opt-in mechanism (a panel registry, `$debugMax`, or per-challenge
  level declarations). Levels are uniform across challenges.
- No change to `fix.php`, whose editor is unconditional and stays at level 0.
- No change to CI. `docker-publish.yml` auto-discovers challenges and needs no
  edit.
- No new linters. The level-1 SQL editor is assembled from linters that already
  exist in `platform/php/editor/linters.js`.

## Decisions

### Level semantics: symptom versus cause

Level 1 reports how the learner's own attempt failed; level 2 discloses the
target's internals. The rule is normative in the spec because it is what keeps
future challenges consistent without a per-challenge negotiation.

*Alternatives considered.* **Tooling-only level 1** (editor and linting, zero
extra information) draws the sharpest conceptual line, but leaves the SQLi rungs
with a level 1 that changes nothing meaningful — their inputs are one-line fields
and their whole story is in the error and the query. **Attacker-side versus
victim-side** ("level 1 shows only what browser DevTools could already get")
produces almost the same split but rules out the `sqli-blind` timing panel, which
that challenge's own README calls the learner's instrument. **Per-challenge
author choice** was rejected as under-constrained: it makes "flip it to Hints" a
sentence the shared docs cannot say.

### Renumber rather than alias

`debug=1` becomes Hints and `debug=2` becomes Debug, and the twenty-three
documentation references are swept in this change. The alternatives all preserve
old links at the cost of a numbering that does not sort (`debug=hints` alongside
`debug=1`) or a second parameter to explain in every README. No screenshots exist
yet (`docs/img/` holds only a placeholder README), so nothing visual goes stale.

An old `?debug=1` bookmark now lands on the less-revealing level. For a teaching
tool that is the safe direction to fail in: a learner sees fewer spoilers than
they expected, not more.

### A three-option `<select>` in the header

The control becomes a pico-styled `<select>` with options Off / Hints / Debug,
keeping the existing inline `onchange` pattern but setting the parameter to a
value instead of adding and deleting it. It is plain HTML5, needs no custom CSS,
self-labels the active level as text, fits the existing header `<nav><li>` slot,
and absorbs a fourth level for free.

*Alternatives considered.* A **range slider** reads best as a dial but cannot
label its own ticks without custom CSS. **Two independent switches** (Editor,
Internals) expose the two axes honestly but cost header width on every page and
force a ruling on the meaningless "internals without editor" state. **Progressive
disclosure** (a second switch appearing after the first) preserves today's
first-run look but hides level 2 behind a two-step interaction.

### `debug.php` lives in the harness, not the PHP family

```php
$debugLevel  = max(0, min(2, (int)($_GET['debug'] ?? 0)));
$debugSuffix = $debugLevel > 0 ? '?debug=' . $debugLevel : '';
function debug_switch(): void { /* pico <select>, inline onchange */ }
```

Pages `require 'debug.php';` at the top, call `debug_switch()` in the nav, and
gate panels with `if ($debugLevel >= 1)` / `>= 2`. `(int)` casting gives the
clamping and the non-numeric handling the spec requires with no extra branches.

It belongs in `platform/harness/` rather than `platform/php/` because it contains
nothing PHP-family-specific — it is part of the same web-delivery skeleton as
`fix.php`, and a future non-PHP family inherits it unchanged.

The helpers stop at values and the control. Panel-wrapping helpers
(`debug_panel(2, fn)`) were rejected: closures around HTML read badly in a
codebase that is otherwise plain alternating-syntax templates, and the challenge
pages are meant to stay readable by learners who fork them.

### A separate `cm-sql-input.js` bundle for level-1 SQLi input

New `platform/php/editor/cm-sql-input.js` attaches to
`[data-codemirror="sql-input"]`: MySQL mode, the existing `mysqlStringHighlighter`
(so quote boundaries colour the way MySQL parses them, backslash escapes
included), `lintGutter`, and `sqlBadCommentLinter` — the `--`-needs-a-space rule,
a genuine gotcha learners hit constantly. It is form-wired like
`cm-html-edit.js`: it writes back into the textarea on submit.

`sqlUnterminatedStringLinter` is deliberately excluded. An unterminated string is
exactly what a breakout payload produces; flagging it would tell the learner they
had made a mistake at the moment they succeeded. This is why the existing
`cm-sql-edit.js` cannot be reused as-is — it is a read/scratch view of the
server's query, where an unterminated string genuinely is an error.

The SQLi input fields change from `<input type="text">` to
`<textarea data-codemirror="sql-input" rows="1">`, mirroring what the XSS pages
already do at debug time, so the level-0 markup stays a plain input.

### `querySelectorAll` across all bundles

Every `cm-*.js` currently does `document.querySelector(...)` and wires exactly one
element. `sqli-login` has two injectable fields (username and password), so level
1 there needs both upgraded. All four existing bundles plus the new one move to
`querySelectorAll` with per-element wiring. This is a prerequisite, not a
cleanup — the spec's "every exploitable field is upgraded" scenario fails
without it.

### Level-2 content for the three challenges that have none

`xss-light`, `xss-shop`, and `hello` have no cause-side output today; with the
editor moving to level 1 their level 2 would be empty, and a dial position that
changes nothing reads as broken.

- `xss-light`, `xss-shop`: show the raw `$_GET['q']` / `$_POST['comment']` exactly
  as PHP received it (making URL-decoding and quote handling visible), plus a
  read-only `php-view` of `critical.php` exposing the unescaped echo.
- `hello`: the read-only `critical.php` view moves to level 1 — it is the tour's
  entire payload and there is no vulnerability to spoil — and level 2 adds a
  request-internals panel (`$_GET`, `$_POST`, and relevant headers as PHP parsed
  them), giving the on-ramp challenge an honest preview of what Debug means
  further up the ladder.

### Panel placement

| challenge | level 1 | level 2 |
| --- | --- | --- |
| `intro/hello` | read-only `critical.php` | request internals (`$_GET`, `$_POST`, headers) |
| `web/sqli-login` | `sql-input` editor on username and password; real `$db->error` | `$sql` editor; returned rows |
| `web/sqli-blind` | `sql-input` editors; `$db->error`; query runtime, CPU, block I/O | `$sql` editor; returned rows |
| `web/sqli-insert` | `sql-input` editors; `$db->error` | `$sql` editor; full user table |
| `web/xss-light` | `html-edit` editor on `q` | raw `$_GET['q']`; read-only `critical.php` |
| `web/xss-shop` | `html-edit` editor on comment | raw `$_POST['comment']`; read-only `critical.php` |
| `web/xss-cookie/index` | — | — |
| `web/xss-cookie/search` | `html-edit` editor on `q`; own session cookie | raw `$_GET['q']`; read-only `critical.php` |
| `web/xss-cookie/chat` | admin bot's JS console errors | admin's rendered page source |

Two placements deserve their reasoning recorded:

- **`sqli-blind`'s timing panel at level 1.** That challenge's README already
  separates "the timing panel is your instrument" from "the rows and SQL are
  there to check your reasoning" — instrument versus check maps exactly onto
  symptom versus cause. Caveat: a server-measured query duration is cleaner than
  the wall-clock a real attacker reads, so level 1 remains a friendlier
  instrument than reality. That is acceptable for a teaching rung and is called
  out in the challenge README.
- **`xss-cookie/search`'s session cookie at level 1.** The cookie is set without
  `HttpOnly`, so `document.cookie` already exposes it at level 0. The panel
  reveals nothing the learner could not read in DevTools.

## Risks / Trade-offs

- **Old `?debug=1` links silently change meaning** → Swept in the same change
  across all twenty-three references; the failure mode for a missed one is
  showing *less* than expected, which is the safe direction. The archived
  `2026-08-30-restructure-repo` change keeps its `debug=1` references as a
  historical record and is deliberately not edited.
- **The `sessionStorage` form-restore scripts in `sqli-login` and `sqli-blind`
  read `[name=username]` on submit** → CodeMirror must copy its document into the
  textarea first. `cm-html-edit.js` already solves this with a
  `capture: true` submit listener; `cm-sql-input.js` uses the same pattern, and
  the pages' own listeners stay non-capturing.
- **Nine pages each requiring a new file is one more thing that can be forgotten
  in a new challenge** → The requirement is spec-level and the failure is loud:
  omitting `require 'debug.php'` leaves `debug_switch()` undefined and the page
  fatals immediately at build-verify time.
- **A one-row `<textarea>` replacing a one-line `<input>` changes SQLi form feel
  (Enter inserts a newline instead of submitting)** → Accepted; it is the same
  trade the XSS challenges already make at debug time, and MySQL treats the
  newline as whitespace, so a stray one does not break a payload.
- **Level 1 on the SQLi rungs shows the raw `$db->error`, which leaks table and
  column names in some MySQL messages** → Intended. That is precisely the symptom
  a real error-based injection reads, and it stops short of the query and the
  rows.
- **More surface to keep consistent across challenges with no test suite** →
  Mitigated by centralising in `debug.php`, and by a manual click-through matrix
  in `tasks.md` covering every challenge at all three levels.

## Migration Plan

Build order matters: `platform/harness/` then `platform/php/` then challenges, as
challenge images layer on the base chain. `debug.php` and the rebuilt bundles
only reach a challenge after its base images are rebuilt, so the base chain is
rebuilt first and the challenge pages are converted after.

Rollback is a git revert; images are rebuilt from source and nothing persists
between runs.
