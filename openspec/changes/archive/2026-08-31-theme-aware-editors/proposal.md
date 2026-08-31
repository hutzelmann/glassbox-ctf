## Why

Every CodeMirror editor in the repo renders in CodeMirror's light theme, always,
because no bundle configures a theme at all. Only `pico.css` reacts to
`prefers-color-scheme`. On a browser set to dark, the page around the editor is
dark (`#13171f`), the editor body is transparent so it shows that dark page, and
CodeMirror's base theme paints the gutter with its hardcoded light values:
`background #f5f5f5`, `color #6c6c6c`, active line gutter `#e2f2ff`. The result
is a bright vertical strip down the left of every editor.

The same defect runs deeper than the strip. `basicSetup` installs
`defaultHighlightStyle`, a palette drawn for a white background, so on a dark
page the PHP open tag and comments render at `rgb(64,71,64)` on `rgb(19,23,31)`,
close to invisible. The editor is where the learner reads the vulnerable snippet
and writes the fix, so a half-legible editor is a direct hit to the core
mechanism of the project.

## What Changes

- Add a shared theme module to each platform family's editor sources
  (`platform/php/editor/cm-theme.js`, `platform/native/editor/cm-theme.js`),
  exporting one extension that carries both the editor chrome theme and a syntax
  highlight style.
- Every bundle in both families (`cm-init`, `cm-php-view`, `cm-html-edit`,
  `cm-sql-edit`, `cm-sql-input` on the PHP side; `cm-init`, `cm-c-view` on the
  native side) installs that extension, so all seven editors follow the page.
- The chrome colours are pico variables (`--pico-color`,
  `--pico-form-element-background-color`, `--pico-muted-color`,
  `--pico-muted-border-color`, `--pico-text-selection-color`, the dropdown
  variables for panels and tooltips), so the editor tracks whatever pico is
  showing without a second source of truth for the palette.
- The token palette, which pico has no variables for, is written with the CSS
  `light-dark()` function. Pico sets `color-scheme` on `:root` per theme, so
  `light-dark()` resolves against pico's own decision rather than against a
  second media query that could disagree with it.
- No JavaScript reads `matchMedia` and no extension is swapped at runtime: the
  scheme switch happens entirely in CSS, so a scheme change mid-session needs no
  reload and no reconfiguration.
- Two things that were painting themselves light are brought under the theme:
  `mysql-strings.js`, which marks MySQL string ranges with a hardcoded `#a11`
  copied from CodeMirror's default palette, now reads the theme's string colour;
  and the search panel's own field and buttons, which the base theme paints white
  with a light gradient, now use pico's form and secondary variables.

## Capabilities

### New Capabilities

None. This is a property of the editors the existing capability already
describes.

### Modified Capabilities

- `challenge-structure`: adds a requirement that the in-browser editors render in
  the same colour scheme as the page around them, in both directions, and that
  the highlighting stays legible in both.

## Impact

- `platform/php/editor/`: one new file, five bundles gain one extension entry,
  `package.json` gains an explicit `@lezer/highlight` dependency (today it is
  only reachable transitively through `@codemirror/language`).
- `platform/native/editor/`: the same, for two bundles.
- Both family Dockerfiles: the new source file joins the `COPY` line of the
  esbuild stage.
- Every challenge image inherits the fix through a base-chain rebuild. No
  challenge file changes, no page changes, no `fix.php` change.
- `AGENTS.md`: the editor stack section gains the theme module, and the "No
  custom CSS" convention gains the boundary that makes this legal.
- No CI change: no new image, no new build stage, no new fetch.
