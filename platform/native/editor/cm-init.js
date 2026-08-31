import { EditorView, basicSetup } from "codemirror";
import { cpp } from "@codemirror/lang-cpp";
import { cLinter, treeLinter, lintGutter } from "./linters.js";
import { glassboxTheme } from "./cm-theme.js";

// Editable C editor for fix.php. Attaches to the same textarea[name='content']
// the harness editor uses, so fix.php loads this bundle by name (codemirror-bundle.js)
// with no PHP-vs-C awareness, the family layer supplies the language.
document.addEventListener("DOMContentLoaded", () => {
  const textarea = document.querySelector("textarea[name='content']");
  if (!textarea) return;

  textarea.hidden = true;

  const view = new EditorView({
    doc: textarea.value,
    extensions: [basicSetup, glassboxTheme, cpp(), EditorView.lineWrapping, lintGutter(), cLinter, treeLinter],
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
