## Why

Switching the debug dial after submitting loses the result: the dial does a plain GET
to `?debug=<n>`, so the prior POST (the learner's run) is gone and they must re-submit
to see the same attempt at the new level. This papercut is not specific to the binary
rungs — every challenge whose attempt is a POST (the SQLi logins, the XSS order form)
has it, and inspecting your own submission at a deeper level is the whole point of the
dial. So the dial should re-run the last submission by default, everywhere, and only
skip it where replaying the submit would be unsafe. Separately, the payload-derived
stack table's bytes column can wrap mid-word on narrow widths (the live table already
prevents this), which makes the hex hard to read.

## What Changes

- **The debug dial re-runs the last submission on a level switch, by default.**
  Changing the dial re-POSTs the page's primary submitted form at the new level (the
  first `POST` form that is not opted out and has a filled input), so the result is
  preserved in one click. If there is nothing to re-run (no such form), it navigates as
  before. GET forms are unaffected — the dial's GET branch already carries their query
  params across the level change.
- **Opt-OUT, per form.** A form whose submit is not safe to replay marks itself
  `data-debug-no-resubmit`; the dial then skips it and falls back to navigation. This
  replaces the previous opt-in attribute. `sqli-insert`'s **register** form (which
  performs an INSERT) opts out; its logout form is button-only and is never a resubmit
  candidate. Every other challenge (single idempotent POST form, or GET/no form) needs
  no change.
- **The stack table's bytes and value columns never wrap** — add `white-space:nowrap`
  to the payload-derived table's byte/value cells, matching the live table.

## Capabilities

### Modified Capabilities

- `challenge-structure`: the debug dial re-runs a page's last submission when the level
  changes, by default, preserving the result at the new level; a form may opt out when
  its submit is not safe to repeat.

## Impact

- **Harness** (`platform/harness/debug.php`): `debug_switch()` wires the dial via a
  script that re-POSTs the primary non-opted-out submitted form, else navigates.
  Rebuilds the harness base image and everything below it (all challenges inherit it).
- **Platform** (`platform/native/native-run.php`): `nrun_stack_table` byte/value cells
  get `white-space:nowrap`.
- **Challenges**: `sqli-insert` register form gains `data-debug-no-resubmit`; the two
  binary forms drop their now-redundant opt-in attribute. No other challenge changes.
- **Out of scope:** caching submissions server-side; any change to the debug levels'
  content; preserving a non-idempotent submit's result across a level switch (it opts
  out and is re-entered).
