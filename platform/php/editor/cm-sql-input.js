// Level-1 editor for the fields a SQLi challenge is exploited through.
//
// Deliberately NOT sqlUnterminatedStringLinter: an unterminated string is what a
// breakout payload produces, so flagging it would tell the learner they made a
// mistake at the exact moment they succeeded. sqlBadCommentLinter stays, because
// "-- " needing a trailing space is a real MySQL rule learners trip over.
import { EditorView, basicSetup } from "codemirror";
import { sql, MySQL } from "@codemirror/lang-sql";
import { lintGutter, sqlBadCommentLinter } from "./linters.js";
import { mysqlStringHighlighter } from "./mysql-strings.js";

document.addEventListener("DOMContentLoaded", () => {
  const editors = [];

  document.querySelectorAll('[data-codemirror="sql-input"]').forEach((textarea) => {
    textarea.hidden = true;

    const view = new EditorView({
      doc: textarea.value,
      extensions: [
        basicSetup,
        sql({ dialect: MySQL }),
        EditorView.lineWrapping,
        lintGutter(),
        sqlBadCommentLinter,
        mysqlStringHighlighter,
      ],
    });

    const wrapper = document.createElement("div");
    wrapper.style.cssText = "border: var(--pico-border-width) solid var(--pico-form-element-border-color); border-radius: var(--pico-border-radius); margin-bottom: var(--pico-spacing); overflow: hidden;";
    wrapper.appendChild(view.dom);
    textarea.insertAdjacentElement("afterend", wrapper);

    editors.push({ textarea, view });
  });

  if (!editors.length) return;

  // Capture phase: the page's own submit handlers (the sessionStorage form
  // restore) read the textarea values, so they must already be up to date.
  document.addEventListener("submit", (e) => {
    for (const { textarea, view } of editors) {
      if (textarea.form === e.target) textarea.value = view.state.doc.toString();
    }
  }, { capture: true });

  document.addEventListener("reset", (e) => {
    setTimeout(() => {
      for (const { textarea, view } of editors) {
        if (textarea.form !== e.target) continue;
        view.dispatch({
          changes: { from: 0, to: view.state.doc.length, insert: textarea.value },
        });
      }
    });
  });
});
