// The shared CodeMirror theme every editor bundle in this family installs.
// This is the native family's copy of the php family's file; each family's build
// stage only sees its own editor/ directory, the same reason linters.js exists
// once per family. Keep the two in step.
//
// Without it CodeMirror renders in its built-in light mode always: the editing
// surface has no background of its own, so it shows the dark page through it,
// while the gutter keeps the base theme's hardcoded #f5f5f5. The editor is where
// the learner reads the vulnerable snippet and writes the fix, so it has to
// follow the page in both directions.
//
// Two sources of colour, and no third:
//   - chrome comes from pico's own variables, so the page's stylesheet stays the
//     single source of truth and the editor cannot disagree with the page;
//   - the token palette, which pico publishes no variables for, uses the CSS
//     light-dark() function. pico sets color-scheme on :root, and light-dark()
//     resolves against exactly that, so the palette follows pico's decision
//     rather than a second reading of the browser preference. Nothing here reads
//     matchMedia, so a scheme change needs no reload and no reconfiguration.
//
// The palette is also published as custom properties on the editor. Nothing in
// this family reads them yet; they are part of the shared file because the php
// family's mysql-strings.js paints MySQL string ranges by hand and needs the
// same string colour this style uses.
import { EditorView } from "codemirror";
import { HighlightStyle, syntaxHighlighting } from "@codemirror/language";
import { tags as t } from "@lezer/highlight";

// A tint over the editing surface rather than one of pico's surface variables:
// pico's card and form-element backgrounds are different colours in its dark
// theme but the same colour in its light one, so a surface variable would drop
// the light-mode gutter strip entirely.
const gutterTint = "light-dark(rgba(0, 0, 0, 0.03), rgba(255, 255, 255, 0.04))";
const activeTint = "light-dark(rgba(0, 0, 0, 0.05), rgba(255, 255, 255, 0.06))";

const editorChrome = EditorView.theme({
  "&": {
    color: "var(--pico-color)",
    backgroundColor: "var(--pico-form-element-background-color)",
    "--gb-cm-comment": "light-dark(#656d76, #8b949e)",
    "--gb-cm-keyword": "light-dark(#cf222e, #ff7b72)",
    "--gb-cm-string": "light-dark(#0a3069, #a5d6ff)",
    "--gb-cm-literal": "light-dark(#0550ae, #79c0ff)",
    "--gb-cm-function": "light-dark(#8250df, #d2a8ff)",
    "--gb-cm-type": "light-dark(#116329, #7ee787)",
    "--gb-cm-invalid": "light-dark(#82071e, #ffa198)",
  },
  ".cm-content": { caretColor: "var(--pico-color)" },
  ".cm-cursor, .cm-dropCursor": { borderLeftColor: "var(--pico-color)" },
  ".cm-gutters": {
    backgroundColor: gutterTint,
    color: "var(--pico-muted-color)",
    borderRight: "var(--pico-border-width) solid var(--pico-muted-border-color)",
  },
  ".cm-activeLine": { backgroundColor: activeTint },
  ".cm-activeLineGutter": { backgroundColor: activeTint },
  // Spelled out rather than shortened: the base theme's own selection rules are
  // more specific than a bare class, so a short selector would lose to them and
  // keep painting the light selection.
  "&.cm-focused > .cm-scroller > .cm-selectionLayer .cm-selectionBackground": {
    backgroundColor: "var(--pico-text-selection-color)",
  },
  ".cm-selectionBackground": { backgroundColor: "var(--pico-text-selection-color)" },
  ".cm-panels, .cm-tooltip": {
    backgroundColor: "var(--pico-dropdown-background-color)",
    color: "var(--pico-color)",
    border: "var(--pico-border-width) solid var(--pico-dropdown-border-color)",
  },
  // The search panel's own widgets, which the base theme paints white with a
  // light gradient. Left alone they are a white field holding light grey text.
  ".cm-textfield": {
    backgroundColor: "var(--pico-form-element-background-color)",
    color: "var(--pico-form-element-color)",
    border: "var(--pico-border-width) solid var(--pico-form-element-border-color)",
  },
  ".cm-button": {
    backgroundImage: "none",
    backgroundColor: "var(--pico-secondary-background)",
    color: "var(--pico-secondary-inverse)",
    border: "var(--pico-border-width) solid var(--pico-secondary-border)",
  },
  ".cm-foldPlaceholder": {
    backgroundColor: "var(--pico-code-background-color)",
    color: "var(--pico-muted-color)",
    border: "var(--pico-border-width) solid var(--pico-muted-border-color)",
  },
  ".cm-specialChar": { color: "var(--gb-cm-invalid)" },
});

// Punctuation, operators and plain variables are left out on purpose: they
// inherit --pico-color, which is the page's own text colour in either scheme.
const editorHighlight = HighlightStyle.define([
  { tag: [t.comment, t.meta], color: "var(--gb-cm-comment)", fontStyle: "italic" },
  { tag: [t.keyword, t.modifier, t.self, t.operatorKeyword], color: "var(--gb-cm-keyword)" },
  { tag: [t.string, t.regexp, t.escape, t.attributeValue], color: "var(--gb-cm-string)" },
  { tag: [t.number, t.bool, t.null, t.atom], color: "var(--gb-cm-literal)" },
  { tag: [t.function(t.variableName), t.function(t.propertyName), t.macroName], color: "var(--gb-cm-function)" },
  { tag: [t.typeName, t.className, t.tagName, t.namespace], color: "var(--gb-cm-type)" },
  { tag: [t.propertyName, t.attributeName], color: "var(--gb-cm-literal)" },
  { tag: t.invalid, color: "var(--gb-cm-invalid)" },
]);

export const glassboxTheme = [editorChrome, syntaxHighlighting(editorHighlight)];
