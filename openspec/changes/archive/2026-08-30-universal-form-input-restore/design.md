## Context

See proposal.md, Why. The behaviour being generalised is not browser bfcache.
The challenge pages branch on `!empty($_POST)` and render the form only in the
GET branch, and the way back is an ordinary link (`<a href="./">Back to
Login</a>`), i.e. a fresh navigation. So the browser has nothing to restore, and
the existing scripts fill the gap by hand.

Three constraints shape the design:

1. **No per-form configuration.** Configuration is what produced the six missing
   pages and the silent `xss-shop` bug.
2. **Invisible enough not to teach the wrong lesson.** A learner reading the page
   source must be able to dismiss it in one glance.
3. **It must never win against the server.** `fix.php` writes the pristine
   snippet on Restore; the search pages echo `?q=` back into the field; the
   binary rungs echo the submitted payload back. Anything that overwrites
   server-rendered state silently corrupts the lesson.

## Goals / Non-Goals

**Goals:**
- One helper, zero per-field configuration, covering every form on every page.
- Server-rendered values always win.
- Survives a change of debug level.
- Correct in the presence of the CodeMirror editors.

**Non-Goals:**
- Cross-tab or cross-session persistence. `sessionStorage` per tab is the point.
- Per-challenge isolation of stored values (see Decision 6).
- Restoring fields the form does not submit (the binary escape view has no
  `name`; it is derived from the hex field instead, see Decision 8).

## Decisions

### 1. Restore only into fields the server left blank

A field is refilled only when it is blank: empty value, nothing checked in its
name-group, or, for `<select>`, no `<option selected>` in the markup.

The alternative, "restore when the field still equals its default", collapses
into unconditional restore, because on a fresh load every field equals its
default.

`data-no-restore` on a form or a single field opts out. It has no in-tree use
today, because `fix.php` is protected structurally by not loading the helper at
all, which is stronger than an opt-out, but it is the documented escape hatch for
the next challenge that needs one.

Consequence: `xss-shop`'s quantity inputs render `value="0"` and would no longer
qualify. Rather than special-case `0` for number inputs, the attribute is
dropped. Verified harmless: `(int)"" === 0`, so `$ordered` still filters the item
out and `$exceeded` still passes, and the page renders the same "no items
selected" branch it renders for all-zeros today.

### 2. Identity = pathname + sorted editable field names

Keys look like `form-input:/|password,username`. Buttons and hidden fields are
excluded from the signature, which buys two things: the hidden `debug` marker on
the search forms does not invalidate the key when the level changes, and
`login_submit` versus `register_submit` is not load-bearing.

Rejected: form index on the page, because `sqli-insert` hides both forms once
logged in, so indices are not stable. Rejected: one blob per page keyed by field
name, because it relies on names never colliding across forms on a page, which is
luck rather than design.

Two degenerate forms fall out correctly for free: `sqli-insert`'s logout form and
`search.php`'s login/logout form contain only a button and a hidden field, so
their signature is empty and they are skipped.

The query string is deliberately not part of the key, which is what makes
surviving the debug dial work. This is safe only because field *names* are stable
across levels: level 1 replaces `<input name="q">` with `<textarea name="q">`,
and `<input name="username">` with `<textarea name="username">`, so the signature
is unchanged. A future page that renamed a field per level would silently lose
the entry, which is why the spec states the survival requirement explicitly.

### 3. Save on `pagehide`, never clear

The debug dial is a `<select>` whose `onchange` calls `window.location.replace()`,
not a submit, so a `submit` listener cannot see it. `pagehide` fires on every way
of leaving a page (submit, link, `location.replace`, back, tab close), so it is a
single listener that replaces the old submit handler entirely.

With `pagehide` re-saving on every exit, consuming the entry on restore is dead
weight that only makes the behaviour order-dependent, so the entry is never
cleared. The one case that could conflict does not: after a POST the web pages do
not render the form at all, so `pagehide` on a result page finds no matching
signature and leaves the stored entry alone. The binary pages do keep their form
after a POST, but they also re-render the submitted payload into it, so what
`pagehide` stores is the same value.

### 4. Non-deferred script tag, first in `<head>`

The CodeMirror bundles are `defer` scripts that mount an `EditorView` from
`textarea.value` inside their own `DOMContentLoaded` handler. Whoever registers
that handler first wins.

A plain (non-`defer`) script executes during parse, so its `DOMContentLoaded`
listener is registered before any deferred script has even run, regardless of tag
order. The helper therefore restores first, and CodeMirror mounts from the
already-restored value.

This is why `xss-shop`'s comment restore was broken: the inline script sat at the
end of `<body>`, registered second, and its write was later overwritten by
CodeMirror's capture-phase submit handler. Rejected alternatives: poking the
CodeMirror view through an exported hook (couples the helper to every bundle);
delaying editor init by a tick (makes init timing load-bearing and invisible).

The `sql-edit`, `php-view` and `c-view` textareas are not inside any form, so
they are out of scope automatically.

### 5. Harness layer, explicit per-page tag, not in `fix.php`

The helper is browser-side and language-agnostic, the same argument that puts
`fix.php`, `debug.php` and `pico.css` in the harness rather than in a family. The
native family builds `FROM` the harness, so the binary challenges inherit the
file without a Dockerfile change.

Inclusion is an explicit `<script>` tag per page rather than Apache-level
injection (`mod_substitute`). Injection would not actually hide anything, since
the tag appears in the rendered HTML either way; it would only make the tag's
origin ungreppable, which is worse for the stated goal, not better. Emitting it
from `debug.php`, which every page already requires, was also rejected: that file
renders the dial and owns the debug contract, and burying an unrelated `<script>`
in it trades one clean line per page for a surprise in a shared file.

Only the nine form-bearing pages get the tag. `hello/index.php`,
`xss-cookie/index.php` and `log.php` have no forms, and `hello` in particular is
the page where reading the source *is* the lesson, so a dead script tag there is
pure noise. `fix.php` is excluded on purpose, and the reason is worth stating precisely,
because the obvious one is wrong. Restoring the original does not race the
helper: `fix.php` writes the pristine file, redirects, and the follow-up GET
re-reads it from disk, so the textarea is non-blank and the blank-only rule
already refuses to touch it.

The real hazard is that every field in `fix.php` shows the file currently on
disk, so a *blank* field means the file genuinely is empty, and both fields can
legitimately be empty: `save` writes `$_POST['content']` with no emptiness check,
and the binary family's `fields[build.flags]` is written unconditionally, with
`nbuild` compiling happily with no flags at all. Refilling either from a previous
session would leave the editor showing something other than what is actually
running, and the next Save would write that stale text back over it. Not loading
the helper is the only mechanism available: `fix.php` is shipped by the harness
and has no form id to target.

### 6. Accepted: values carry across containers on the same port

`sessionStorage` is origin-scoped and every challenge runs on `localhost:9000`,
so stopping one challenge and starting another in the same tab reuses the key.
Two families of collision exist in-tree: `form-input:/|password,username` is
shared by `sqli-login`, `sqli-blind` and `sqli-insert`'s login form, and
`form-input:/|payload_hex` is shared by `ret2win` and `ret2libc`.

The binary pair is the sharper case and the reason this decision is worth
writing down. Both rungs use the same buffer size, so a stale `ret2win` chain
pre-fills `ret2libc` as a structurally plausible payload whose target address
does not exist there, and the learner gets a segfault with nothing indicating the
bytes were not theirs.

Accepted rather than fixed. Mixing challenge identity into the key needs
something on the page that identifies the challenge, and nothing does
(`document.title` is "Admin Login" on both `sqli-login` and `sqli-blind`); a
build-time token would reintroduce exactly the per-challenge configuration this
change removes. The collision only ever happens between consecutive rungs of one
ladder reusing a single tab and port, the field is visibly pre-filled rather than
silently submitted, and typing over it costs nothing.

### 7. Fail silently

The whole helper is wrapped in `try/catch`. `sessionStorage` access throws
outright when site data is blocked, and a page-breaking error in a comfort script
is the worst possible outcome, particularly in `xss-cookie`, where the debug view
is itself a JavaScript-error console and any noise muddies the lesson. Malformed
entries are ignored rather than repaired.

### 8. A refill announces itself as an input event

The binary rungs give the learner two views of one payload: a readable
`\xNN` escape textarea and the hex textarea that is actually submitted. Only the
hex field has a `name`, so only it is stored and restored. The page keeps the two
in step with `input` listeners, plus a one-shot mirror on load for the value a
POST echoed back; that inline script runs during parse, well before the helper's
`DOMContentLoaded` restore. A silent write would therefore leave the escape view
empty next to a populated hex field, and the learner's next keystroke in the
escape view would wipe the restored payload.

So the helper dispatches the event that typing would have raised (`input` for
text-like fields, `change` for checkables and selects) after every write. This is
decoupled: pages express their own field relationships in the ordinary way, and
the helper does not know about any of them.

### 9. In-form editors sync continuously, not on submit

`cm-html-edit.js` and `cm-sql-input.js` wrote their document back into the
underlying textarea only on `submit`. That is enough for submitting, but not for
`pagehide`: leaving the page any other way, above all moving the debug dial,
would store a stale, empty `textarea.value` and silently discard what the learner
typed into the editor. That is the `xss-shop` comment box and every SQLi payload
field at level 1.

Both now sync on every document change via an `updateListener`, so the textarea
is always current no matter how the page is left. This is decoupled: the editor
knows nothing about the helper, it simply keeps its own backing field accurate,
which it arguably should have done all along. The submit handlers stay as
belt-and-braces.

Only these two need it: they are the only bundles whose textarea lives inside a
`<form>`. `cm-sql-edit.js`, `cm-php-view.js` and the native `cm-c-view.js` attach
to standalone debug-panel textareas, and `cm-init.js` belongs to `fix.php`, which
does not load the helper at all.

## Risks / Trade-offs

- **A new page can forget the tag.** Mitigated by documenting it in AGENTS.md as
  part of the page skeleton. Accepted as cheaper than Apache-level injection.
- **A future page could rename a field per debug level**, which would break the
  key (Decision 2). No page does today; the spec pins the behaviour so a
  regression shows up as a failed scenario rather than a mystery.
- **Passwords are stored in plain `sessionStorage`.** Deliberate: every
  credential in this repo is fictional teaching content, the container is
  throwaway and offline, and excluding password fields would make a learner's
  registration payload vanish while their username survived, with nothing on the
  page explaining why.
- **Dispatching input events could wake unrelated page logic.** Reviewed every
  `input`/`change` listener in the tree: the escape/hex mirror and the endianness
  helper on the binary pages, and the debug dial. The dial has no `name` and sits
  outside every form, so the helper never touches it; the endianness helper's
  fields are not in a form either.
- **`<select>` restores only when the server expressed no preference.** No
  `<select>` inside a form exists in-tree; the conservative reading is the safe
  one.
