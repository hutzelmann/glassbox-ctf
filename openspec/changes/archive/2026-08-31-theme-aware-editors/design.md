## Context

See proposal.md, Why. Three facts about the current state shape the design, all
measured in a running `sqli-login` container with the browser in dark mode:

1. **The editors are in CodeMirror's light mode and nothing can move them.** The
   editor root carries the classes `cm-editor ͼ1 ͼ2 ͼ4`, that is the base theme,
   the base theme's *light* scope, and `defaultHighlightStyle`. The light scope
   is selected by the `EditorView.darkTheme` facet, whose default is `false`, and
   no bundle sets it. So `.cm-gutters` resolves to `background #f5f5f5; color
   #6c6c6c`, and `.cm-activeLineGutter` to `#e2f2ff`, on a page whose background
   is `#13171f`.
2. **The editing surface has no background of its own.** `.cm-editor` and
   `.cm-content` both compute to `rgba(0,0,0,0)`, so the body of the editor is
   whatever pico painted behind it. That is why the bug looks like "dark editor,
   light gutter" rather than "the whole editor is light".
3. **Pico declares the scheme, and it is reachable from CSS.** `pico.min.css`
   sets `color-scheme: light` and `color-scheme: dark` on `:root` in its theme
   blocks, and `color-scheme` inherits. The `light-dark()` CSS function resolves
   against exactly that, and the browsers these containers are used from support
   it (verified: `CSS.supports('color','light-dark(#000,#fff)')` is true, and a
   probe element resolved to its dark argument under pico's dark theme).

The repo convention "No custom CSS. Use pico.css classes/semantics only" governs
page markup: challenge pages must not grow stylesheets. A CodeMirror theme is not
page CSS, it is editor configuration that CodeMirror injects at runtime, and it
is the only place the editor's own chrome can be described at all. The bundles
already reach for pico variables in the wrapper `<div>` they build around each
view (`--pico-border-width`, `--pico-form-element-border-color`,
`--pico-border-radius`, `--pico-spacing`), so "editor chrome described with pico
variables" is an established pattern here rather than a new one.

## Goals / Non-Goals

**Goals:**

- One theme, installed by every editor bundle a family ships, so no editor can be
  left behind (the read-only `c-view` bundle is currently mounted by no page, and
  it still gets the theme, because the next page to use it should not have to
  remember).
- The page's stylesheet stays the single source of truth for the palette. The
  theme reads pico variables and does not restate their values.
- No JavaScript decision about the colour scheme. The switch is CSS, so it costs
  nothing at runtime and cannot get out of step with pico.

**Non-Goals:**

- A theme picker. The page follows the browser preference and nothing offers to
  override it; the editor inherits whatever that produces.
- Restyling the editor beyond colour. Fonts, spacing, and the wrapper `<div>`
  keep their current values.
- Colours for editor features nothing in this repo mounts (autocompletion
  tooltips are not configured today; the panel and tooltip rules exist because
  the search panel and the lint tooltip are reachable from `basicSetup`).

## Decisions

### 1. A theme module per family, installed by every bundle

`cm-theme.js` sits next to the other editor sources in each family and exports a
single extension array (the chrome theme plus the highlight style). Each bundle
adds it to its `extensions` list right after `basicSetup`.

Why after `basicSetup`: `basicSetup` installs `defaultHighlightStyle` with
`{fallback: true}`, which means "use this only when no other highlighter is
present". Adding a real highlighter therefore replaces it rather than fighting
it, which is the documented composition and the same one `@codemirror/theme-one-dark`
relies on.

The file is duplicated across `platform/php/editor/` and
`platform/native/editor/` rather than shared. Each family's Dockerfile copies
only its own `editor/` directory into the node build stage, and `linters.js`
already exists once per family for the same reason. Hoisting it to the harness
was rejected: the harness ships no JavaScript build stage at all, and adding one
so that two families can share about forty lines would put a node stage into the
base image that every family pays for.

### 2. Chrome from pico variables, palette from nothing else

Everything the theme can express as a pico variable is expressed as one:
`--pico-color` for text and caret, `--pico-form-element-background-color` for the
editing surface (the wrapper already draws a form-element border around it, so
the editor should read as a form control), `--pico-muted-color` for line numbers,
`--pico-muted-border-color` for the gutter separator,
`--pico-text-selection-color` for the selection, and the dropdown variables for
the search panel and lint tooltip.

The alternative, a hand-written light palette plus a hand-written dark palette
for the chrome, was rejected because it would duplicate values pico already
publishes and would drift the moment pico is upgraded.

### 3. The gutter is a tint over the surface, not a second pico surface variable

The obvious choice for the gutter, `--pico-card-sectioning-background-color`,
is a different colour from the form surface in pico's dark theme
(`rgb(26,30,40)` against `rgb(28,33,44)`) but *the same colour* in its light
theme (both `rgb(251,252,252)`). Using it would silently drop today's light-mode
appearance, where the gutter is a distinct strip, and leave the light scheme
relying on the separator line alone.

So the gutter and the active line are painted as a translucent tint over the
editing surface: `light-dark(rgba(0,0,0,.03), rgba(255,255,255,.04))` and
friends. That keeps the same relationship in both schemes, and in light mode it
lands within a couple of values of the `#f5f5f5` the base theme uses today.

### 4. `light-dark()` rather than `matchMedia` or an embedded media query

The token palette has no pico variables to read, so its two sets of values have
to live in the theme. Three ways to select between them were considered:

- **`light-dark()`** (chosen). Resolves against the used `color-scheme`, which
  pico sets on `:root`. So the editor follows pico by construction, including a
  future page that pins `data-theme`, and it follows a mid-session scheme change
  with no reload.
- **`@media (prefers-color-scheme: dark)` inside the theme.** Works, but it
  reads the browser preference directly, a second opinion that would disagree
  with pico the moment a page pins a theme.
- **`matchMedia` in JavaScript plus a `Compartment` reconfigure on change.**
  Rejected: it is the only option that needs a listener per editor and a
  reconfiguration path, and it buys nothing the CSS options do not already have.

### 5. Rejected: `EditorView.darkTheme` and a ready-made dark theme

Setting the `darkTheme` facet would flip the base theme to its `&dark` rules,
but the facet is a static boolean, so it would need the `matchMedia` machinery of
option three, and it would land on CodeMirror's own dark greys (`#333338`
gutters) rather than pico's. `@codemirror/theme-one-dark` has the same problem
plus a fixed `#282c34` background that would sit inside a pico card as a visibly
foreign panel, and it adds a dependency to both families while still leaving the
light scheme to be written by hand.

### 6. The overriding selectors have to beat the base theme

Theme styles are mounted after base-theme styles, so at equal specificity the
theme wins, which is what makes plain `.cm-gutters` enough (verified: the gutter
computed to the themed dark value with the prototype bundle loaded).

Two base rules are more specific than a bare class and must be matched in kind,
or they will keep their light values:

- the focused selection, `&.cm-focused > .cm-scroller > .cm-selectionLayer
  .cm-selectionBackground`, which the base theme paints `#d7d4f0`,
- the unfocused selection's sibling rule for `::selection`.

The theme therefore writes those selectors out in full rather than relying on the
short form.

### 7. A named token palette, not pico's semantic colours

Pico publishes no syntax colours (pico 2 dropped the `--pico-code-*-color` set
that pico 1 had; only `--pico-code-background-color` and `--pico-code-color`
remain). Reusing semantic variables such as `--pico-del-color` and
`--pico-ins-color` for keywords and types would give three hues for six token
classes and would tie the meaning of "string" to the meaning of "deleted".

The palette is instead a small set of `light-dark()` pairs on the GitHub light
and dark values, chosen because both halves are contrast-checked against
near-white and near-black surfaces and they are the colours most learners have
already seen. Tags covered: comment and meta, keyword, string, number and atom,
function, type and class and tag name, property and attribute name, and invalid.
Punctuation and plain variables are deliberately left to inherit `--pico-color`.

### 8. `@lezer/highlight` becomes an explicit dependency

The tag vocabulary comes from `@lezer/highlight`. Today it is only present as a
transitive dependency of `@codemirror/language`, so importing it directly while
leaving it out of `package.json` would work by accident and break on any hoisting
change. Both families list it explicitly, pinned like their other CodeMirror
packages.

### 9. Everything that paints itself has to join the theme

Two places paint colours outside the highlight style, and both were light-only:

- `mysql-strings.js` decorates MySQL string ranges with an inline
  `style="color: #a11"`, a value copied from `defaultHighlightStyle` so that the
  hand-painted ranges matched the ones CodeMirror coloured. Now that the string
  colour comes from the theme, the mark has to follow it or the level-2 query
  view gets two different reds. This is why the theme publishes its palette as
  `--gb-cm-*` custom properties on the editor as well as consuming it: the mark
  reads `var(--gb-cm-string)`, so there is still exactly one string colour.
- The search panel (`Ctrl-F`, reachable from `basicSetup`) builds a
  `.cm-textfield` and `.cm-button`, which the base theme paints white and a light
  gradient. Themed panels alone left a white field holding light grey text. Both
  now take pico's form-element and secondary variables.

Verified in a rebuilt container: the panel field reads `rgb(28,33,44)` with
`rgb(224,227,231)` text in dark, and the hand-painted MySQL strings resolve to
the same `rgb(165,214,255)` the highlight style uses.

## Risks / Trade-offs

- **`light-dark()` needs a 2023-or-later browser.** → The container is opened by
  the learner's own browser, and the same page already depends on pico 2's own
  modern CSS. A browser too old for `light-dark()` falls back to the *first*
  argument, which is the light value, which is exactly today's behaviour rather
  than a regression.
- **The theme depends on pico variable names.** → A pico major upgrade could
  rename them, and the editor would fall back to unstyled colours. Mitigated by
  the same variables already being used by the wrapper `<div>` in every bundle,
  so an upgrade that broke them would be visible immediately, not silently.
- **Two copies of the theme file to keep in step.** → Accepted, consistent with
  `linters.js`. The tasks list touching both, and the requirement is worded so
  that an editor missed in either family is a failed scenario.
- **A stale podman layer can ship the old bundle**, which for a purely visual fix
  reads as "the change did not work". → Verification rebuilds the base chain
  before looking, and AGENTS.md already documents `--no-cache` for this class of
  confusion.
