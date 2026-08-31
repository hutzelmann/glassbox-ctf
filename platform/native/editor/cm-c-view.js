import { EditorView, basicSetup } from "codemirror";
import { cpp } from "@codemirror/lang-cpp";
import { glassboxTheme } from "./cm-theme.js";

// Read-only C view: used by the debug page to show the uneditable main.c context
// (main() + win() + the harness around the learner's snippet).
document.addEventListener("DOMContentLoaded", () => {
  const textarea = document.querySelector('[data-codemirror="c-view"]');
  if (!textarea) return;

  textarea.hidden = true;

  const view = new EditorView({
    doc: textarea.value,
    extensions: [
      basicSetup,
      glassboxTheme,
      cpp(),
      EditorView.lineWrapping,
      EditorView.editable.of(false),
    ],
  });

  const wrapper = document.createElement("div");
  wrapper.style.cssText = "border: var(--pico-border-width) solid var(--pico-form-element-border-color); border-radius: var(--pico-border-radius); margin-bottom: var(--pico-spacing); overflow: hidden;";
  wrapper.appendChild(view.dom);
  textarea.insertAdjacentElement("afterend", wrapper);
});
