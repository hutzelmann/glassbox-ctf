import { EditorView, basicSetup } from "codemirror";
import { ViewPlugin, Decoration } from "@codemirror/view";
import { RangeSetBuilder } from "@codemirror/state";
import { sql, MySQL } from "@codemirror/lang-sql";
import { treeLinter, lintGutter, sqlUnterminatedStringLinter, sqlBadCommentLinter } from "./linters.js";

// Parses string ranges using MySQL escape rules (\' and \\ handled).
function getMysqlStringRanges(text) {
  const ranges = [];
  let inString = false;
  let start = 0;
  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    if (!inString) {
      if (ch === "'") { inString = true; start = i; }
      else if (ch === '#' || (ch === '-' && text[i+1] === '-' && (i+2 >= text.length || /\s/.test(text[i+2])))) {
        while (i < text.length && text[i] !== '\n') i++;
      } else if (ch === '/' && text[i+1] === '*') {
        i += 2;
        while (i < text.length - 1 && !(text[i] === '*' && text[i+1] === '/')) i++;
        i++;
      }
    } else if (ch === '\\') {
      i++; // skip MySQL-escaped character (\' or \\, etc.)
    } else if (ch === "'") {
      if (text[i+1] === "'") { i++; } // SQL '' escape, stay in string
      else { ranges.push({ from: start, to: i + 1 }); inString = false; }
    }
  }
  if (inString) ranges.push({ from: start, to: text.length });
  return ranges;
}

// Parses string ranges the same way CodeMirror's SQL tokenizer does:
// no backslash escape, only '' to escape a quote.
function getCm6StringRanges(text) {
  const ranges = [];
  let inString = false;
  let start = 0;
  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    if (!inString) {
      if (ch === "'") { inString = true; start = i; }
      else if (ch === '#' || (ch === '-' && text[i+1] === '-' && (i+2 >= text.length || /\s/.test(text[i+2])))) {
        while (i < text.length && text[i] !== '\n') i++;
      } else if (ch === '/' && text[i+1] === '*') {
        i += 2;
        while (i < text.length - 1 && !(text[i] === '*' && text[i+1] === '/')) i++;
        i++;
      }
    } else if (ch === "'") {
      if (text[i+1] === "'") { i++; } // SQL '' escape
      else { ranges.push({ from: start, to: i + 1 }); inString = false; }
    }
  }
  if (inString) ranges.push({ from: start, to: text.length });
  return ranges;
}

// Returns the portions of sortedA not covered by any range in sortedB.
// Both inputs must be sorted by .from and internally non-overlapping.
function subtractRanges(sortedA, sortedB) {
  const result = [];
  let bi = 0;
  for (const a of sortedA) {
    let pos = a.from;
    while (bi < sortedB.length && sortedB[bi].to <= pos) bi++;
    let j = bi;
    while (j < sortedB.length && sortedB[j].from < a.to) {
      if (pos < sortedB[j].from) result.push({ from: pos, to: sortedB[j].from });
      pos = Math.max(pos, sortedB[j].to);
      j++;
    }
    if (pos < a.to) result.push({ from: pos, to: a.to });
  }
  return result;
}

// color: #a11 matches @codemirror/language defaultHighlightStyle tags.string
const mysqlStringMark = Decoration.mark({ attributes: { style: "color: #a11" } });
// Removes the wrong string color CM6 applied to regions MySQL doesn't see as strings
const noStringMark = Decoration.mark({ attributes: { style: "color: unset" } });

const mysqlStringHighlighter = ViewPlugin.fromClass(class {
  constructor(view) { this.decorations = this.build(view); }
  update(u) { if (u.docChanged) this.decorations = this.build(u.view); }
  build(view) {
    const builder = new RangeSetBuilder();
    const text = view.state.doc.toString();
    const mysqlRanges = getMysqlStringRanges(text);
    const cm6Ranges = getCm6StringRanges(text);
    // CM6 thinks "string" but MySQL doesn't → strip the wrong color
    const wrongRanges = subtractRanges(cm6Ranges, mysqlRanges);
    // Merge and sort; the two sets are non-overlapping by construction
    const all = [
      ...mysqlRanges.map(r => ({ ...r, mark: mysqlStringMark })),
      ...wrongRanges.map(r => ({ ...r, mark: noStringMark })),
    ].sort((a, b) => a.from - b.from);
    for (const { from, to, mark } of all) builder.add(from, to, mark);
    return builder.finish();
  }
}, { decorations: v => v.decorations });

document.addEventListener("DOMContentLoaded", () => {
  const textarea = document.querySelector('[data-codemirror="sql-edit"]');
  if (!textarea) return;

  textarea.hidden = true;

  const view = new EditorView({
    doc: textarea.value,
    extensions: [
      basicSetup,
      sql({ dialect: MySQL }),
      EditorView.lineWrapping,
      lintGutter(),
      treeLinter,
      sqlUnterminatedStringLinter,
      sqlBadCommentLinter,
      mysqlStringHighlighter,
    ],
  });

  const wrapper = document.createElement("div");
  wrapper.style.cssText = "border: var(--pico-border-width) solid var(--pico-form-element-border-color); border-radius: var(--pico-border-radius); margin-bottom: var(--pico-spacing); overflow: hidden;";
  wrapper.appendChild(view.dom);
  textarea.insertAdjacentElement("afterend", wrapper);
});
