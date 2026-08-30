# Screenshots and media

Drop real captures here and reference them from the top-level `README.md`. The
README currently marks these slots inline; replace each note with an image once
captured.

Wanted shots:

- `hero.gif` — the full loop on one challenge: exploit lands, the debug dial goes
  Hints → Debug and reveals the internals, Fix button patches `critical.php`,
  exploit now fails.
- `debug-hints.png` — a challenge at `?debug=1`: the input as a highlighting
  editor with a diagnostic, and the raw database error — no query, no rows.
- `debug-full.png` — the same challenge at `?debug=2`, showing the exact SQL and
  returned rows.
- `fix-editor.png` — the in-browser CodeMirror editor (`fix.php`) with a patched
  snippet.
- `catalog.png` — optional, the challenge list.

Keep them small (the repo ships offline; large media bloats clones). Prefer
optimized PNG/GIF.
