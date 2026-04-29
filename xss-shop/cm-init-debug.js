import { EditorView, basicSetup } from "codemirror";
import { html } from "@codemirror/lang-html";

document.addEventListener("DOMContentLoaded", () => {
  const textarea = document.querySelector("textarea[name='comment']");
  if (!textarea) return;

  textarea.hidden = true;

  const view = new EditorView({
    doc: textarea.value,
    extensions: [basicSetup, html(), EditorView.lineWrapping],
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
