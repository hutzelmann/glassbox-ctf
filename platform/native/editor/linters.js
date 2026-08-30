import { linter, lintGutter } from "@codemirror/lint";
import { syntaxTree } from "@codemirror/language";

// Server-backed C linter: POSTs the snippet to lint.php, which assembles it into
// the real translation unit and runs `gcc -fsyntax-only`, returning whitelisted
// diagnostics keyed to critical.c line numbers (via the #line directive).
export const cLinter = linter(async (view) => {
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

// Cheap in-browser squiggles for gross syntax errors, no server round-trip.
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

export { lintGutter };
