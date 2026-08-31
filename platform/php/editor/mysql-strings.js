// MySQL string-literal handling shared by the two SQL editors: the read/scratch
// view of the server's assembled query (cm-sql-edit) and the learner's payload
// input (cm-sql-input). CodeMirror's SQL tokenizer does not know MySQL's
// backslash escapes, so the colouring it produces is wrong exactly where an
// injection payload is interesting.
import { ViewPlugin, Decoration } from "@codemirror/view";
import { RangeSetBuilder } from "@codemirror/state";

// Parses string ranges using MySQL escape rules (\' and \\ handled).
export function getMysqlStringRanges(text) {
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
export function getCm6StringRanges(text) {
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
export function subtractRanges(sortedA, sortedB) {
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

// The string colour cm-theme.js publishes, so a hand-painted MySQL string looks
// like every other string and follows the page's colour scheme with them.
const mysqlStringMark = Decoration.mark({ attributes: { style: "color: var(--gb-cm-string)" } });
// Removes the wrong string color CM6 applied to regions MySQL doesn't see as strings
const noStringMark = Decoration.mark({ attributes: { style: "color: unset" } });

export const mysqlStringHighlighter = ViewPlugin.fromClass(class {
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
