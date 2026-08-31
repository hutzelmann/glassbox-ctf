## 1. PHP family theme

- [x] 1.1 Write `platform/php/editor/cm-theme.js`: an `EditorView.theme` built on pico variables plus a `HighlightStyle` of `light-dark()` pairs, exported as one extension array
- [x] 1.2 Write the focused-selection selector out in full, so it beats the base theme's more specific light rule
- [x] 1.3 Theme the search panel's own widgets (`.cm-textfield`, `.cm-button`), which the base theme paints white with a light gradient
- [x] 1.4 Publish the palette as `--gb-cm-*` custom properties and point `mysql-strings.js` at `var(--gb-cm-string)` instead of its hardcoded `#a11`
- [x] 1.5 Add `@lezer/highlight` to `platform/php/editor/package.json`, pinned like the other CodeMirror packages
- [x] 1.6 Install the theme in all five bundles: `cm-init.js`, `cm-php-view.js`, `cm-html-edit.js`, `cm-sql-edit.js`, `cm-sql-input.js`
- [x] 1.7 Add `editor/cm-theme.js` to the `COPY` line of the node stage in `platform/php/Dockerfile`

## 2. Native family theme

- [x] 2.1 Write `platform/native/editor/cm-theme.js` with the same body, and a header comment noting it is the per-family copy
- [x] 2.2 Add `@lezer/highlight` to `platform/native/editor/package.json`
- [x] 2.3 Install the theme in both bundles: `cm-init.js`, `cm-c-view.js`
- [x] 2.4 Add `editor/cm-theme.js` to the `COPY` line of the node stage in `platform/native/Dockerfile`

## 3. Documentation

- [x] 3.1 `AGENTS.md`: name `cm-theme.js` in the editor stack section for both families
- [x] 3.2 `AGENTS.md`: state the boundary that keeps this inside the "No custom CSS" convention, page styling stays pico-only and the editor's own chrome is editor configuration built from pico variables
- [x] 3.3 Keep every added line free of em dashes, per the Plain punctuation convention

## 4. Verification

- [x] 4.1 Rebuild `glassbox-php` and `glassbox-native`, then `sqli-login`, `xss-light`, `hello`, and `ret2win`
- [x] 4.2 `fix.php` in dark: editing surface `rgb(28,33,44)`, gutter a 4% white tint over it, separator from `--pico-muted-border-color`. Gutter against the page went from 16.5:1 (the old `#f5f5f5` strip) to 1.25:1
- [x] 4.3 `fix.php` in light: surface `rgb(251,252,252)`, gutter a 3% black tint over it, so the strip is still visible
- [x] 4.4 Tokens legible in both: every colour is at least 4.4:1 against the surface, against 1.8:1 to 2.4:1 for comments, keywords and strings before the change
- [x] 4.5 `sqli-login` at levels 1 and 2: both payload editors and the assembled-query view are themed, and the hand-painted MySQL strings resolve to the same colour as the highlight style
- [x] 4.6 `xss-light` (`html-edit`) and `hello` (read-only `php-view`) are themed
- [x] 4.7 `ret2win`'s `fix.php` (the C editor) is themed
- [x] 4.8 Caret is `--pico-color`, the focused selection is `--pico-text-selection-color`, the active line is the tint, and the search panel's field and buttons are pico's
- [x] 4.9 A lint diagnostic tooltip is dark with pico text and its severity bar
- [x] 4.10 Switching the browser scheme with a page open moves the editor with no reload
