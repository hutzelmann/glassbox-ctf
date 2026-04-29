import { EditorView, basicSetup } from "codemirror";
import { sql, MySQL } from "@codemirror/lang-sql";
import { treeLinter, lintGutter, sqlUnterminatedStringLinter } from "./linters.js";

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
    ],
  });

  const wrapper = document.createElement("div");
  wrapper.style.cssText = "border: var(--pico-border-width) solid var(--pico-form-element-border-color); border-radius: var(--pico-border-radius); margin-bottom: var(--pico-spacing); overflow: hidden;";
  wrapper.appendChild(view.dom);
  textarea.insertAdjacentElement("afterend", wrapper);
});
