import { EditorView, basicSetup } from "codemirror";
import { html } from "@codemirror/lang-html";
import { treeLinter, htmlTagLinter, jsLinter, lintGutter } from "./linters.js";

document.addEventListener("DOMContentLoaded", () => {
  const editors = [];

  document.querySelectorAll('[data-codemirror="html-edit"]').forEach((textarea) => {
    textarea.hidden = true;

    // Keep the backing textarea current on every change, not only on submit:
    // this editor can sit inside a form, and leaving the page any other way (a
    // link, the debug dial) must not lose what the learner typed.
    const syncTextarea = EditorView.updateListener.of((update) => {
      if (update.docChanged) textarea.value = update.state.doc.toString();
    });

    const view = new EditorView({
      doc: textarea.value,
      extensions: [basicSetup, html(), EditorView.lineWrapping, lintGutter(), treeLinter, htmlTagLinter, jsLinter, syncTextarea],
    });

    const wrapper = document.createElement("div");
    wrapper.style.cssText = "border: var(--pico-border-width) solid var(--pico-form-element-border-color); border-radius: var(--pico-border-radius); margin-bottom: var(--pico-spacing); overflow: hidden;";
    wrapper.appendChild(view.dom);
    textarea.insertAdjacentElement("afterend", wrapper);

    editors.push({ textarea, view });
  });

  if (!editors.length) return;

  // Capture phase: the page's own submit handlers read the textarea values, so
  // they must already be up to date.
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
