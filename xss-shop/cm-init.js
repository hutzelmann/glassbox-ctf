import { EditorView, basicSetup } from "codemirror";
import { php } from "@codemirror/lang-php";

document.addEventListener("DOMContentLoaded", () => {
  const textarea = document.querySelector("textarea[name='content']");
  if (!textarea) return;

  textarea.hidden = true;

  const view = new EditorView({
    doc: textarea.value,
    extensions: [basicSetup, php(), EditorView.lineWrapping],
  });

  const wrapper = document.createElement("div");
  wrapper.style.cssText = "border: var(--pico-border-width) solid var(--pico-form-element-border-color); border-radius: var(--pico-border-radius); margin-bottom: var(--pico-spacing); overflow: hidden;";
  wrapper.appendChild(view.dom);
  textarea.insertAdjacentElement("afterend", wrapper);

  textarea.form.addEventListener("submit", () => {
    textarea.value = view.state.doc.toString();
  });

  textarea.form.addEventListener("reset", () => {
    setTimeout(() => {
      view.dispatch({
        changes: { from: 0, to: view.state.doc.length, insert: textarea.value },
      });
    });
  });
});
