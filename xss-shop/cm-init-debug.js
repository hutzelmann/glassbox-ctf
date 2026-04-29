import { EditorView, basicSetup } from "codemirror";
import { html } from "@codemirror/lang-html";
import { linter, lintGutter } from "@codemirror/lint";
import { syntaxTree } from "@codemirror/language";
import { esLint } from "@codemirror/lang-javascript";
import { Linter } from "eslint-linter-browserify";
import globals from "globals";

const VALID_HTML_TAGS = new Set([
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

const treeLinter = linter((view) => {
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

const htmlTagLinter = linter((view) => {
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

const jsLinter = linter(esLint(new Linter(), {
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

document.addEventListener("DOMContentLoaded", () => {
  const textarea = document.querySelector("textarea[name='comment']");
  if (!textarea) return;

  textarea.hidden = true;

  const view = new EditorView({
    doc: textarea.value,
    extensions: [basicSetup, html(), EditorView.lineWrapping, lintGutter(), treeLinter, htmlTagLinter, jsLinter],
  });

  const wrapper = document.createElement("div");
  wrapper.style.cssText = "border: var(--pico-border-width) solid var(--pico-form-element-border-color); border-radius: var(--pico-border-radius); margin-bottom: var(--pico-spacing); overflow: hidden;";
  wrapper.appendChild(view.dom);
  textarea.insertAdjacentElement("afterend", wrapper);

  document.addEventListener("submit", (e) => {
    if (e.target === textarea.form) {
      textarea.value = view.state.doc.toString();
    }
  }, { capture: true });

  textarea.form.addEventListener("reset", () => {
    setTimeout(() => {
      view.dispatch({
        changes: { from: 0, to: view.state.doc.length, insert: textarea.value },
      });
    });
  });
});
