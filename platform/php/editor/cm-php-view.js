import { EditorView, basicSetup } from "codemirror";
import { php } from "@codemirror/lang-php";

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll('[data-codemirror="php-view"]').forEach((textarea) => {
    textarea.hidden = true;

    const view = new EditorView({
      doc: textarea.value,
      extensions: [
        basicSetup,
        php(),
        EditorView.lineWrapping,
        EditorView.editable.of(false),
      ],
    });

    const wrapper = document.createElement("div");
    wrapper.style.cssText = "border: var(--pico-border-width) solid var(--pico-form-element-border-color); border-radius: var(--pico-border-radius); margin-bottom: var(--pico-spacing); overflow: hidden;";
    wrapper.appendChild(view.dom);
    textarea.insertAdjacentElement("afterend", wrapper);
  });
});
