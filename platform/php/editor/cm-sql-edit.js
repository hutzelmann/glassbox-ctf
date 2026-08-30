// Level-2 view of the query the server actually assembled. Editable so the
// learner can poke at it, but not wired to any form: nothing is sent back.
import { EditorView, basicSetup } from "codemirror";
import { sql, MySQL } from "@codemirror/lang-sql";
import { treeLinter, lintGutter, sqlUnterminatedStringLinter, sqlBadCommentLinter } from "./linters.js";
import { mysqlStringHighlighter } from "./mysql-strings.js";

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll('[data-codemirror="sql-edit"]').forEach((textarea) => {
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
});
