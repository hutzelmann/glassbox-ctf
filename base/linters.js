import { linter, lintGutter } from "@codemirror/lint";
import { syntaxTree } from "@codemirror/language";
import { esLint } from "@codemirror/lang-javascript";
import { Linter } from "eslint-linter-browserify";
import globals from "globals";

export const VALID_HTML_TAGS = new Set([
  "a", "abbr", "address", "area", "article", "aside", "audio",
  "b", "base", "bdi", "bdo", "blockquote", "body", "br", "button",
  "canvas", "caption", "cite", "code", "col", "colgroup",
  "data", "datalist", "dd", "del", "details", "dfn", "dialog", "div", "dl", "dt",
  "em", "embed", "fieldset", "figcaption", "figure", "footer", "form",
  "h1", "h2", "h3", "h4", "h5", "h6", "head", "header", "hgroup", "hr", "html",
  "i", "iframe", "img", "input", "ins", "kbd",
  "label", "legend", "li", "link", "main", "map", "mark", "menu", "meta", "meter",
  "nav", "noscript", "object", "ol", "optgroup", "option", "output",
  "p", "picture", "pre", "progress", "q", "rp", "rt", "ruby",
  "s", "samp", "script", "search", "section", "select", "slot", "small",
  "source", "span", "strong", "style", "sub", "summary", "sup",
  "table", "tbody", "td", "template", "textarea", "tfoot", "th", "thead",
  "time", "title", "tr", "track", "u", "ul", "var", "video", "wbr",
]);

export const phpLinter = linter(async (view) => {
  if (view.state.doc.length === 0) return [];
  const code = view.state.doc.toString();
  const diagnostics = [];
  try {
    const resp = await fetch("lint.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "code=" + encodeURIComponent(code),
    });
    if (!resp.ok) return diagnostics;
    const data = await resp.json();
    for (const d of data) {
      try {
        const line = view.state.doc.line(d.line);
        diagnostics.push({ from: line.from, to: line.to, severity: d.severity, message: d.message });
      } catch {}
    }
  } catch {}
  return diagnostics;
});

export const treeLinter = linter((view) => {
  if (view.state.doc.length === 0) return [];
  const diagnostics = [];
  syntaxTree(view.state).iterate({
    enter: (node) => {
      if (node.type.isError) {
        diagnostics.push({
          from: node.from,
          to: Math.max(node.from + 1, node.to),
          severity: "warning",
          message: "Syntax error",
        });
      }
    },
  });
  return diagnostics;
});

export const htmlTagLinter = linter((view) => {
  if (view.state.doc.length === 0) return [];
  const diagnostics = [];
  const doc = view.state.doc;
  syntaxTree(view.state).iterate({
    enter: (node) => {
      if (node.name === "TagName") {
        const tag = doc.sliceString(node.from, node.to).toLowerCase();
        if (!VALID_HTML_TAGS.has(tag)) {
          diagnostics.push({
            from: node.from,
            to: node.to,
            severity: "warning",
            message: `<${tag}> is not a standard HTML element`,
          });
        }
      }
    },
  });
  return diagnostics;
});

export const jsLinter = linter(esLint(new Linter(), {
  languageOptions: {
    ecmaVersion: 2020,
    sourceType: "script",
    globals: globals.browser,
  },
  rules: {
    "no-undef": "error",
    "no-unused-vars": "warn",
  },
}));

export { lintGutter };
