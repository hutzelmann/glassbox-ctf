## 1. Platform: shared debug plumbing

- [x] 1.1 Create `platform/harness/debug.php` providing `$debugLevel` (clamped
      int 0–2 from `$_GET['debug']`), `$debugSuffix` (`?debug=<n>` or empty), and
      `debug_switch()` rendering the pico `<select>` with Off / Hints / Debug,
      the current level pre-selected, and an inline `onchange` that sets the
      `debug` parameter and replaces the URL
- [x] 1.2 Add `debug.php` to the `COPY` in `platform/harness/Dockerfile`
- [x] 1.3 Rebuild `glassbox-harness` and confirm `debug.php` is present in the
      web root

## 2. Platform: editor bundles

- [x] 2.1 Change `cm-init.js`, `cm-php-view.js`, `cm-html-edit.js`, and
      `cm-sql-edit.js` from `document.querySelector` to `querySelectorAll` with
      per-element wiring (each element gets its own `EditorView`, wrapper, and
      submit/reset listeners)
- [x] 2.2 Create `platform/php/editor/cm-sql-input.js` targeting
      `[data-codemirror="sql-input"]`: MySQL mode, `mysqlStringHighlighter`,
      `lintGutter`, `sqlBadCommentLinter`; explicitly NOT
      `sqlUnterminatedStringLinter`; form-wired with a `capture: true` submit
      listener that writes the document back into the textarea
- [x] 2.3 Extract `mysqlStringHighlighter` (and its range helpers) out of
      `cm-sql-edit.js` so both SQL bundles share one copy
- [x] 2.4 Add the `cm-sql-input.js` esbuild target and the
      `codemirror-sql-input.js` `COPY` to `platform/php/Dockerfile`
- [x] 2.5 Rebuild `glassbox-php` and confirm all five bundles are in the web root

## 3. Intro challenge

- [x] 3.1 `challenges/intro/hello/index.php`: require `debug.php`, replace the
      switch with `debug_switch()`, move the read-only `critical.php` view to
      level 1, and add a level-2 request-internals panel (`$_GET`, `$_POST`, and
      relevant headers as PHP parsed them)

## 4. SQLi challenges

- [x] 4.1 `sqli-login/index.php`: require `debug.php` and `debug_switch()`; at
      level 1+ render username and password as
      `<textarea data-codemirror="sql-input" rows="1">` and load
      `codemirror-sql-input.js`; show the real `$db->error` at level 1; keep the
      `$sql` editor and the returned-rows table at level 2 only
- [x] 4.2 Verify the `sessionStorage` form-restore script in `sqli-login` still
      round-trips both fields at every level (CodeMirror's capturing submit
      listener must run before the page's own)
- [x] 4.3 `sqli-blind/index.php`: same conversion; place the timing / CPU /
      block-I/O panel at level 1 alongside `$db->error`; keep `$sql` and rows at
      level 2
- [x] 4.4 `sqli-insert/index.php`: same conversion; `$db->error` at level 1;
      `$sql` editor and the full user table at level 2 (note this page uses
      `$isDebug` as well as `$debugSuffix` — replace both)

## 5. XSS challenges

- [x] 5.1 `xss-light/index.php`: require `debug.php` and `debug_switch()`; the
      `html-edit` textarea for `q` moves to level 1; add a level-2 panel showing
      the raw `$_GET['q']` as PHP received it plus a read-only `php-view` of
      `critical.php`
- [x] 5.2 `xss-shop/index.php`: same conversion, for `$_POST['comment']`; update
      BOTH header navs (the form page and the confirmation page)
- [x] 5.3 `xss-cookie/index.php`: require `debug.php` and `debug_switch()` (this
      page has no debug output of its own; it only needs the control and sticky
      links)
- [x] 5.4 `xss-cookie/search.php`: `html-edit` textarea for `q` and the session
      cookie panel at level 1; raw `$_GET['q']` and a read-only `php-view` of
      `critical.php` at level 2; the hidden `debug` form field carries
      `$debugLevel`
- [x] 5.5 `xss-cookie/chat.php`: admin bot's JS console errors at level 1; the
      admin's rendered page source (`html-edit`) at level 2 only
- [x] 5.6 `xss-cookie/log.php` and the harness `fix.php` also carried the old
      switch/suffix — converted so the level survives the analytics page and the
      Fix round-trip

## 6. Verification

- [x] 6.1 Build and click through every challenge at levels 0, 1, and 2,
      confirming each level shows something the previous one did not and that the
      header control reflects the active level
- [x] 6.2 Confirm level stickiness on every page: internal links, form
      submissions, and redirects (`sqli-insert`'s reset action,
      `xss-cookie/search`'s login/logout) all preserve the level
- [x] 6.3 Confirm `?debug=7`, `?debug=-1`, `?debug=banana`, and `?debug=` render
      a valid level instead of failing
- [x] 6.4 Confirm level 1 leaks no cause-side output: no assembled query, no
      returned rows, no server source, no victim rendered output on any challenge
- [x] 6.5 Confirm a breakout payload (`' OR 1=1 -- `) produces no red diagnostic
      in the level-1 SQL input editor, while `--` without a trailing space does
- [x] 6.6 Confirm the Fix editor still works at every level and that `lint.php`
      diagnostics still appear

## 7. Documentation

- [x] 7.1 Root `README.md`: rewrite the glass-box bullet to feature the
      three-level dial as a headline capability, and update the quick-start line
      that tells the reader to flip the switch
- [x] 7.2 Update the eight challenge `README.md` files: describe what each level
      shows for that challenge and which level gives the answer away; note in
      `sqli-blind` that its level-1 timing panel is cleaner than a real
      attacker's wall-clock
- [x] 7.3 Update the eight `solution.md` files so every debug reference names the
      level that actually shows the referenced output (`?debug=2` for query,
      rows, and page source)
- [x] 7.4 Update the glass-box and debug-switch descriptions in `AGENTS.md`,
      including the `debug.php` addition to the harness and the new
      `cm-sql-input.js` bundle in the PHP family
- [x] 7.5 Confirm no live file still references `?debug=1` with the old meaning
      (the archived `2026-08-30-restructure-repo` change stays untouched)

## 8. Close out

- [x] 8.1 Run `openspec validate tiered-debug-levels --strict`
- [x] 8.2 Archive with `openspec archive tiered-debug-levels`
